/**
 * Single loader for the slim autocomplete dataset in public/data/.
 * — Never loads airports.json (verbose); only airports.index.min.json + airports.version.json
 * — One in-memory expanded index after load
 * — IndexedDB holds compact { v, r }; localStorage optionally holds version string only
 * — At most one full index fetch per app session unless version changes (background check)
 */
(() => {
    const VERSION_URL = "/data/airports.version.json";
    const INDEX_URL = "/data/airports.index.min.json";
    const GLOBAL_INDEX_URL = "/airports/index/global";
    const SHOULD_PREFETCH_GLOBAL =
        !["127.0.0.1", "localhost"].includes(window.location.hostname) ||
        window.AIRPORT_PREFETCH_GLOBAL === true;

    /** Optional: quick version hint for debugging / future use (not required for IDB path) */
    const LS_VERSION_KEY = "apnasafar.airports.datasetVersion";

    const IDB_NAME = "apnasafar_airports_v1";
    const IDB_STORE = "kv";
    const IDB_KEY = "autocomplete_compact_v1";

    /** @type {ExpandedRow[]|null} */
    let memoryIndex = null;

    /** @type {string|null} */
    let memoryVersion = null;

    /** True after the first successful load attempt (even if the dataset is empty). */
    let indexLoaded = false;

    /** @type {Promise<ExpandedRow[]>|null} */
    let loadPromise = null;
    /** @type {Promise<void>|null} */
    let globalPrefetchPromise = null;

    /**
     * @typedef {object} ExpandedRow
     * @property {string} iata
     * @property {string} label
     * @property {string} iataLower
     * @property {string} airportLower
     * @property {string} cityLower
     * @property {string} countryLower
     * @property {string} searchNorm — single lowercased, whitespace-normalized haystack for substring match
     */

    const normalizeIata = (value) => {
        const trimmed = String(value || "").trim().toUpperCase();
        return /^[A-Z]{3}$/.test(trimmed) ? trimmed : "";
    };

    const buildLabel = (city, country, airport, iata) =>
        `${city}, ${country} - ${airport} (${iata})`;

    /**
     * Expand compact tuples once; attach normalised search key for fast local filtering.
     * @param {string[][]} tuples
     * @returns {ExpandedRow[]}
     */
    const expandCompactRows = (tuples) => {
        const out = [];
        for (let i = 0; i < tuples.length; i++) {
            const t = tuples[i];
            if (!Array.isArray(t) || t.length < 4) {
                continue;
            }
            const [iata, city, country, airport] = t;
            const iataU = normalizeIata(iata);
            if (iataU === "") {
                continue;
            }
            const cityS = String(city || "").trim();
            const countryS = String(country || "").trim();
            const airportS = String(airport || "").trim();
            const label = buildLabel(cityS, countryS, airportS, iataU);
            const cityLower = cityS.toLowerCase();
            const countryLower = countryS.toLowerCase();
            const airportLower = airportS.toLowerCase();
            const iataLower = iataU.toLowerCase();
            const searchNorm = [cityLower, countryLower, airportLower, iataLower]
                .join(" ")
                .replace(/\s+/g, " ")
                .trim();
            out.push({
                iata: iataU,
                label,
                iataLower,
                airportLower,
                cityLower,
                countryLower,
                searchNorm,
            });
        }
        return out;
    };

    const readLsVersion = () => {
        try {
            const v = localStorage.getItem(LS_VERSION_KEY);
            return typeof v === "string" && v.length ? v : null;
        } catch (_) {
            return null;
        }
    };

    const writeLsVersion = (v) => {
        try {
            if (typeof v === "string" && v.length) {
                localStorage.setItem(LS_VERSION_KEY, v);
            }
        } catch (_) {
            /* optional */
        }
    };

    const idbOpen = () =>
        new Promise((resolve, reject) => {
            if (!window.indexedDB) {
                reject(new Error("no idb"));
                return;
            }
            const req = indexedDB.open(IDB_NAME, 1);
            req.onupgradeneeded = (e) => {
                const db = e.target.result;
                if (!db.objectStoreNames.contains(IDB_STORE)) {
                    db.createObjectStore(IDB_STORE);
                }
            };
            req.onsuccess = () => resolve(req.result);
            req.onerror = () => reject(req.error);
        });

    const idbGet = async () => {
        const db = await idbOpen();
        return new Promise((resolve, reject) => {
            try {
                const tx = db.transaction(IDB_STORE, "readonly");
                const getReq = tx.objectStore(IDB_STORE).get(IDB_KEY);
                getReq.onsuccess = () => resolve(getReq.result ?? null);
                getReq.onerror = () => reject(getReq.error);
            } catch (e) {
                reject(e);
            }
        });
    };

    const idbPut = async (record) => {
        const db = await idbOpen();
        return new Promise((resolve, reject) => {
            try {
                const tx = db.transaction(IDB_STORE, "readwrite");
                tx.objectStore(IDB_STORE).put(record, IDB_KEY);
                tx.oncomplete = () => resolve();
                tx.onerror = () => reject(tx.error);
            } catch (e) {
                reject(e);
            }
        });
    };

    const fetchJson = (url) =>
        fetch(url, { credentials: "same-origin", cache: "no-store", headers: { Accept: "application/json" } }).then(
            (response) => {
                if (!response.ok) {
                    throw new Error("fetch failed");
                }
                return response.json();
            }
        );

    const fetchIndexPayload = () => fetchJson(INDEX_URL);
    const fetchRemoteVersion = () => fetchJson(VERSION_URL).then((m) => (typeof m?.v === "string" ? m.v : null));
    const fetchGlobalIndexPayload = () => fetchJson(GLOBAL_INDEX_URL);

    const persistPayload = async (payload) => {
        if (!payload || typeof payload.v !== "string" || !Array.isArray(payload.r)) {
            return;
        }
        writeLsVersion(payload.v);
        try {
            await idbPut({ v: payload.v, r: payload.r });
        } catch (_) {
            /* IDB unavailable */
        }
    };

    /**
     * After cache hit: ping server version; refresh full index only if hash changed.
     */
    const scheduleBackgroundVersionCheck = () => {
        const localV = memoryVersion;
        if (!localV) {
            return;
        }
        fetchRemoteVersion()
            .then((remoteV) => {
                if (!remoteV || remoteV === localV) {
                    return;
                }
                return fetchIndexPayload().then((data) => {
                    if (!data || typeof data.v !== "string" || !Array.isArray(data.r) || data.v !== remoteV) {
                        return;
                    }
                    memoryVersion = data.v;
                    memoryIndex = expandCompactRows(data.r);
                    persistPayload(data);
                    window.dispatchEvent(new CustomEvent("airport-dataset-updated", { detail: { version: data.v } }));
                });
            })
            .catch(() => {});
    };

    /**
     * Preload merged global index once per page to make world-airport queries local/fast.
     * Runs in background and never blocks UI.
     */
    const prefetchGlobalIndexInBackground = () => {
        if (globalPrefetchPromise) {
            return globalPrefetchPromise;
        }

        globalPrefetchPromise = fetchGlobalIndexPayload()
            .then((data) => {
                if (!data || typeof data.v !== "string" || !Array.isArray(data.r) || data.r.length === 0) {
                    return;
                }
                if (memoryVersion === data.v && Array.isArray(memoryIndex) && memoryIndex.length > 0) {
                    return;
                }

                memoryVersion = data.v;
                memoryIndex = expandCompactRows(data.r);
                return persistPayload(data).then(() => {
                    window.dispatchEvent(new CustomEvent("airport-dataset-updated", { detail: { version: data.v } }));
                });
            })
            .catch(() => {})
            .finally(() => {
                globalPrefetchPromise = null;
            });

        return globalPrefetchPromise;
    };

    const loadFromNetwork = async () => {
        const data = await fetchIndexPayload();
        if (!data || typeof data.v !== "string" || !Array.isArray(data.r)) {
            memoryVersion = null;
            memoryIndex = [];
            return memoryIndex;
        }
        await persistPayload(data);
        memoryVersion = data.v;
        memoryIndex = expandCompactRows(data.r);
        return memoryIndex;
    };

    const loadFromIdb = async () => {
        const rec = await idbGet();
        if (!rec || typeof rec.v !== "string" || !Array.isArray(rec.r) || !rec.r.length) {
            return null;
        }
        memoryVersion = rec.v;
        memoryIndex = expandCompactRows(rec.r);
        writeLsVersion(rec.v);
        scheduleBackgroundVersionCheck();
        return memoryIndex;
    };

    /**
     * Single entry: loads dataset at most once per page (concurrent callers share the same promise).
     * No network per keystroke — only this pipeline.
     * @returns {Promise<ExpandedRow[]>}
     */
    async function loadExpandedIndexOnce() {
        if (indexLoaded) {
            return memoryIndex ?? [];
        }
        if (loadPromise) {
            return loadPromise;
        }

        loadPromise = (async () => {
            try {
                try {
                    const fromIdb = await loadFromIdb();
                    if (fromIdb && fromIdb.length) {
                        indexLoaded = true;
                        if (SHOULD_PREFETCH_GLOBAL) {
                            prefetchGlobalIndexInBackground();
                        }
                        return memoryIndex ?? [];
                    }
                } catch (_) {
                    /* try network */
                }

                await loadFromNetwork();
                indexLoaded = true;
                if (SHOULD_PREFETCH_GLOBAL) {
                    prefetchGlobalIndexInBackground();
                }
                return memoryIndex ?? [];
            } catch (_) {
                memoryIndex = [];
                memoryVersion = null;
                indexLoaded = true;
                return [];
            } finally {
                loadPromise = null;
            }
        })();

        return loadPromise;
    }

    function getExpandedIndexSync() {
        return indexLoaded ? memoryIndex ?? [] : null;
    }

    function getDatasetVersionSync() {
        return memoryVersion;
    }

    window.AirportDatasetLoader = {
        loadExpandedIndexOnce,
        getExpandedIndexSync,
        getDatasetVersionSync,
        /** Expose for tests / debugging */
        _constants: { VERSION_URL, INDEX_URL, INDEX_ONLY: true },
    };
})();

/**
 * Airport combobox UI: debounced local filtering over AirportDatasetLoader (single in-memory index).
 * No fetch per keystroke — all network I/O lives in airport-dataset-loader.js.
 */
(() => {
    if (!window.AirportDatasetLoader) {
        return;
    }

    const { loadExpandedIndexOnce } = window.AirportDatasetLoader;

    const MIN_QUERY_LENGTH = 1;
    const MAX_RESULTS = 15;
    const FILTER_DEBOUNCE_MS = 120;
    const SERVER_SEARCH_URL = "/airports/search";

    const INPUT_PAIRS = [
        {
            input: document.getElementById("flight-from-input"),
            hidden: document.getElementById("flight-from-code"),
            dropdown: document.getElementById("flight-from-dropdown"),
        },
        {
            input: document.getElementById("flight-to-input"),
            hidden: document.getElementById("flight-to-code"),
            dropdown: document.getElementById("flight-to-dropdown"),
        },
    ];

    const PAIRS = INPUT_PAIRS.filter((pair) => pair.input && pair.hidden && pair.dropdown);
    if (!PAIRS.length) {
        return;
    }

    const DEBOUNCE_HANDLES = new WeakMap();
    const OPTION_MAPS = new WeakMap();
    const searchForm = PAIRS[0].input.closest("form");

    const normalizeIata = (value) => {
        const trimmed = String(value || "").trim().toUpperCase();
        return /^[A-Z]{3}$/.test(trimmed) ? trimmed : "";
    };

    const normalizeToken = (value) => String(value || "").trim().toLowerCase();

    /**
     * Local filter only — uses pre-normalised row.searchNorm for substring tier.
     * @param {object[]} index rows from AirportDatasetLoader
     */
    const rankAndSearch = (index, query) => {
        const q = normalizeToken(query);
        if (q.length < MIN_QUERY_LENGTH) {
            return [];
        }

        const exactIata = [];
        const prefixIata = [];
        const prefixCity = [];
        const prefixAirport = [];
        const prefixCountry = [];
        const containsNorm = [];

        for (let i = 0; i < index.length; i++) {
            const row = index[i];
            if (row.iataLower === q) {
                exactIata.push(row);
                continue;
            }
            if (row.iataLower.startsWith(q)) {
                prefixIata.push(row);
                continue;
            }
            if (row.cityLower.startsWith(q)) {
                prefixCity.push(row);
                continue;
            }
            if (row.airportLower.startsWith(q)) {
                prefixAirport.push(row);
                continue;
            }
            if (row.countryLower.startsWith(q)) {
                prefixCountry.push(row);
                continue;
            }
            if (row.searchNorm.includes(q)) {
                containsNorm.push(row);
            }
        }

        return [
            ...exactIata,
            ...prefixIata,
            ...prefixCity,
            ...prefixAirport,
            ...prefixCountry,
            ...containsNorm,
        ].slice(0, MAX_RESULTS);
    };

    const hideDropdown = (pair) => {
        pair.dropdown.classList.add("d-none");
        pair.dropdown.innerHTML = "";
        pair.input.setAttribute("aria-expanded", "false");
        pair._activeIndex = -1;
        pair._results = [];
    };

    const showDropdown = (pair) => {
        pair.dropdown.classList.remove("d-none");
        pair.input.setAttribute("aria-expanded", "true");
    };

    const syncHiddenCode = (pair) => {
        const currentValue = String(pair.input.value || "").trim();
        const optionMap = OPTION_MAPS.get(pair.input) || new Map();
        const mappedCode = optionMap.get(currentValue) || "";
        pair.hidden.value = mappedCode || normalizeIata(currentValue);
    };

    const applySelection = (pair, row) => {
        pair.input.value = row.label;
        pair.hidden.value = row.iata;
        const m = new Map();
        m.set(row.label, row.iata);
        OPTION_MAPS.set(pair.input, m);
        hideDropdown(pair);
        pair.input.focus();
    };

    const renderSuggestions = (pair, results, options) => {
        const { emptyMessage, showEmpty } = options || {};
        pair.dropdown.innerHTML = "";
        const optionMap = new Map();

        if (results.length === 0) {
            if (showEmpty) {
                const li = document.createElement("li");
                li.className = "list-group-item list-group-item-light small text-secondary";
                li.setAttribute("role", "presentation");
                li.textContent = emptyMessage || "No matching airports";
                pair.dropdown.appendChild(li);
                showDropdown(pair);
            } else {
                hideDropdown(pair);
            }
            OPTION_MAPS.set(pair.input, new Map());
            syncHiddenCode(pair);
            return;
        }

        results.forEach((row, idx) => {
            optionMap.set(row.label, row.iata);
            const li = document.createElement("li");
            li.id = `${pair.input.id}-opt-${idx}`;
            li.className = "list-group-item list-group-item-action airport-suggestion-item";
            li.setAttribute("role", "option");
            li.textContent = row.label;
            li.addEventListener("mousedown", (e) => {
                e.preventDefault();
                applySelection(pair, row);
            });
            pair.dropdown.appendChild(li);
        });

        OPTION_MAPS.set(pair.input, optionMap);
        pair._results = results;
        pair._activeIndex = -1;
        syncHiddenCode(pair);
        showDropdown(pair);
    };

    const mergeSuggestions = (localRows, serverRows) => {
        const merged = [];
        const seen = new Set();
        [...localRows, ...serverRows].forEach((row) => {
            if (!row || !row.iata || seen.has(row.iata)) {
                return;
            }
            seen.add(row.iata);
            merged.push(row);
        });
        return merged.slice(0, MAX_RESULTS);
    };

    const runFilter = (pair, rawQuery) => {
        const q = normalizeToken(rawQuery);
        if (q.length < MIN_QUERY_LENGTH) {
            hideDropdown(pair);
            OPTION_MAPS.set(pair.input, new Map());
            syncHiddenCode(pair);
            return;
        }

        const fetchServerMatches = () =>
            fetch(
                `${SERVER_SEARCH_URL}?q=${encodeURIComponent(q)}&limit=${MAX_RESULTS}`,
                { credentials: "same-origin", headers: { Accept: "application/json" } }
            )
                .then((response) => (response.ok ? response.json() : null))
                .then((payload) => {
                    const rows = Array.isArray(payload?.data) ? payload.data : [];
                    return rows
                        .map((r) => {
                            const iata = normalizeIata(r?.iata || "");
                            const label = String(r?.label || "").trim();
                            if (!iata || !label) {
                                return null;
                            }
                            return { iata, label };
                        })
                        .filter(Boolean);
                })
                .catch(() => []);

        loadExpandedIndexOnce().then((index) => {
            const stillCurrent = () => normalizeToken(pair.input.value) === q;
            if (!index || index.length === 0) {
                fetchServerMatches().then((serverRows) => {
                    if (!stillCurrent()) {
                        return;
                    }
                    renderSuggestions(pair, serverRows, {
                        showEmpty: true,
                        emptyMessage: "Airport list unavailable. Refresh the page or try again later.",
                    });
                });
                return;
            }
            const localResults = rankAndSearch(index, q);
            if (!stillCurrent()) {
                return;
            }

            renderSuggestions(pair, localResults, {
                showEmpty: true,
                emptyMessage: "No matching airports",
            });

            // For meaningful queries, always ask server-side search too so global airports
            // appear even when local slim index has many top-market matches.
            if (q.length < 2) {
                return;
            }

            fetchServerMatches().then((serverRows) => {
                if (!stillCurrent()) {
                    return;
                }
                const merged = mergeSuggestions(localResults, serverRows);
                renderSuggestions(pair, merged, {
                    showEmpty: true,
                    emptyMessage: "No matching airports",
                });
            });
        });
    };

    const debounceFilter = (pair, query) => {
        const existing = DEBOUNCE_HANDLES.get(pair.input);
        if (existing) {
            window.clearTimeout(existing);
        }
        const handle = window.setTimeout(() => runFilter(pair, query), FILTER_DEBOUNCE_MS);
        DEBOUNCE_HANDLES.set(pair.input, handle);
    };

    const highlightActive = (pair) => {
        const items = pair.dropdown.querySelectorAll(".airport-suggestion-item");
        items.forEach((el, i) => {
            el.classList.toggle("active", i === pair._activeIndex);
            if (i === pair._activeIndex) {
                pair.input.setAttribute("aria-activedescendant", el.id);
            }
        });
        if (pair._activeIndex < 0) {
            pair.input.removeAttribute("aria-activedescendant");
        }
    };

    const refreshOpenDropdowns = () => {
        PAIRS.forEach((pair) => {
            if (document.activeElement === pair.input) {
                const v = String(pair.input.value || "");
                if (v.trim().length >= MIN_QUERY_LENGTH) {
                    runFilter(pair, v);
                }
            }
        });
    };

    PAIRS.forEach((pair) => {
        pair.input.setAttribute("role", "combobox");
        pair.input.setAttribute("aria-autocomplete", "list");
        pair.input.setAttribute("aria-expanded", "false");
        pair.dropdown.setAttribute("role", "listbox");

        syncHiddenCode(pair);

        pair.input.addEventListener("input", (event) => {
            syncHiddenCode(pair);
            debounceFilter(pair, event.target.value);
        });

        pair.input.addEventListener("focus", (event) => {
            const v = String(event.target.value || "").trim();
            if (v.length >= MIN_QUERY_LENGTH) {
                debounceFilter(pair, v);
            }
        });

        pair.input.addEventListener("keydown", (event) => {
            if (pair.dropdown.classList.contains("d-none")) {
                return;
            }
            const results = pair._results || [];
            if (results.length === 0) {
                return;
            }
            if (event.key === "ArrowDown") {
                event.preventDefault();
                pair._activeIndex = Math.min(pair._activeIndex + 1, results.length - 1);
                highlightActive(pair);
            } else if (event.key === "ArrowUp") {
                event.preventDefault();
                pair._activeIndex = Math.max(pair._activeIndex - 1, -1);
                highlightActive(pair);
            } else if (event.key === "Enter") {
                if (pair._activeIndex >= 0 && results[pair._activeIndex]) {
                    event.preventDefault();
                    applySelection(pair, results[pair._activeIndex]);
                }
            } else if (event.key === "Escape") {
                event.preventDefault();
                hideDropdown(pair);
            }
        });

        pair.input.addEventListener("blur", () => {
            window.setTimeout(() => {
                if (!pair.dropdown.matches(":hover")) {
                    hideDropdown(pair);
                }
            }, 200);
        });

        pair.input.addEventListener("change", () => syncHiddenCode(pair));
    });

    if (searchForm) {
        searchForm.addEventListener("submit", () => {
            PAIRS.forEach((pair) => syncHiddenCode(pair));
        });
    }

    document.addEventListener("click", (e) => {
        PAIRS.forEach((pair) => {
            if (!pair.input.contains(e.target) && !pair.dropdown.contains(e.target)) {
                hideDropdown(pair);
            }
        });
    });

    window.addEventListener("airport-dataset-updated", () => {
        refreshOpenDropdowns();
    });

    loadExpandedIndexOnce().catch(() => {});
})();

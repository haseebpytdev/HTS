<div class="search-card shadow">
    <div class="d-flex flex-wrap gap-3 align-items-center mb-3">
        <div class="form-check m-0">
            <input class="form-check-input" type="radio" name="trip_type" id="one_way" checked>
            <label class="form-check-label" for="one_way">One Way</label>
        </div>
        <div class="form-check m-0">
            <input class="form-check-input" type="radio" name="trip_type" id="round_trip">
            <label class="form-check-label" for="round_trip">Round Trip</label>
        </div>
        <div class="form-check m-0">
            <input class="form-check-input" type="radio" name="trip_type" id="multi_city">
            <label class="form-check-label" for="multi_city">Multi City</label>
        </div>
    </div>

    <div class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label">From</label>
            <input type="text" class="form-control" placeholder="LHE">
        </div>
        <div class="col-md-3">
            <label class="form-label">To</label>
            <input type="text" class="form-control" placeholder="DXB">
        </div>
        <div class="col-md-3">
            <label class="form-label">Departure</label>
            <input type="date" class="form-control">
        </div>
        <div class="col-md-2">
            <label class="form-label">Class</label>
            <select class="form-select">
                <option>Economy</option>
                <option>Business</option>
            </select>
        </div>
        <div class="col-md-1 d-grid">
            <button type="button" class="btn btn-success fw-semibold">Search</button>
        </div>
    </div>
</div>

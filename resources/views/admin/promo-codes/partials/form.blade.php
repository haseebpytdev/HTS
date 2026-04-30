<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label">Code</label>
        <input type="text" name="code" class="form-control form-control-sm" value="{{ old('code', $promoCode?->code) }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">Type</label>
        @php($type = old('discount_type', $promoCode?->discount_type ?? 'percentage'))
        <select name="discount_type" class="form-select form-select-sm">
            <option value="percentage" @selected($type==='percentage')>Percentage</option>
            <option value="fixed" @selected($type==='fixed')>Fixed</option>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">Discount value</label>
        <input type="number" step="0.01" name="discount_value" class="form-control form-control-sm" value="{{ old('discount_value', $promoCode?->discount_value) }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">Max discount amount</label>
        <input type="number" step="0.01" name="max_discount_amount" class="form-control form-control-sm" value="{{ old('max_discount_amount', $promoCode?->max_discount_amount) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Starts at</label>
        <input type="datetime-local" name="starts_at" class="form-control form-control-sm" value="{{ old('starts_at', optional($promoCode?->starts_at)->format('Y-m-d\TH:i')) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Expires at</label>
        <input type="datetime-local" name="expires_at" class="form-control form-control-sm" value="{{ old('expires_at', optional($promoCode?->expires_at)->format('Y-m-d\TH:i')) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Usage limit</label>
        <input type="number" name="usage_limit" class="form-control form-control-sm" value="{{ old('usage_limit', $promoCode?->usage_limit) }}">
    </div>
    <div class="col-md-8">
        <label class="form-label">Description</label>
        <input type="text" name="description" class="form-control form-control-sm" value="{{ old('description', $promoCode?->description) }}">
    </div>
    <div class="col-12">
        <div class="form-check">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" @checked((bool) old('is_active', $promoCode?->is_active ?? true))>
            <label class="form-check-label" for="is_active">Active</label>
        </div>
    </div>
</div>

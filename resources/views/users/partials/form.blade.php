@php
    $member = $member ?? null;
@endphp

<div class="mb-3">
    <label class="form-label" for="name">Name</label>
    <input id="name" name="name" type="text" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $member?->name) }}" required>
    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label class="form-label" for="email">Email</label>
    <input id="email" name="email" type="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $member?->email) }}" required>
    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label class="form-label" for="role">Role</label>
    <select id="role" name="role" class="form-select @error('role') is-invalid @enderror" required>
        @foreach ($roles as $role)
            <option value="{{ $role->value }}" @selected(old('role', $currentRole?->value ?? null) === $role->value)>{{ $role->label() }}</option>
        @endforeach
    </select>
    @error('role') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label class="form-label" for="status">Status</label>
    <select id="status" name="status" class="form-select @error('status') is-invalid @enderror">
        @foreach ($statuses as $status)
            <option value="{{ $status->value }}" @selected(old('status', $member?->status->value ?? 'active') === $status->value)>{{ $status->label() }}</option>
        @endforeach
    </select>
    @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label class="form-label" for="password">Password @if($member)<span class="text-secondary">(leave blank to keep)</span>@endif</label>
    <input id="password" name="password" type="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" @required(! $member)>
    @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-4">
    <label class="form-label" for="password_confirmation">Confirm password</label>
    <input id="password_confirmation" name="password_confirmation" type="password" class="form-control" autocomplete="new-password">
</div>

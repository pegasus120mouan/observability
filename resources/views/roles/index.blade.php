@extends('layouts.app', ['title' => 'Roles'])

@section('content')
    <p class="text-secondary">System roles are shared by every organization. SUPER_ADMIN is a platform flag and cannot be assigned as an organization membership.</p>

    <div class="row g-3">
        @foreach ($roles as $role)
            <div class="col-lg-6">
                <div class="panel h-100">
                    <div class="panel-header">
                        <h2 class="h6 mb-0">{{ $role->name->label() }}</h2>
                        <x-status-badge value="System" variant="secondary" />
                    </div>
                    <p class="text-secondary">{{ $role->description }}</p>
                    <ul class="small mb-0">
                        @foreach ($assignments[$role->name->value] ?? [] as $permission)
                            <li>{{ $catalog[$permission] ?? $permission }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endforeach
    </div>
@endsection

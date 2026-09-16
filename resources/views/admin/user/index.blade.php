@extends('master.master')

@section('content')
    @php
        $isSuperAdmin = optional(optional(auth()->user())->role)->slug === 'super-admin';
    @endphp
    <div class="page-content">
        <nav class="page-breadcrumb d-flex justify-content-between align-items-center flex-wrap gap-2">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Users</li>
            </ol>
            <a href="{{ route('admin.user.create') }}" class="btn btn-primary">Create User</a>
        </nav>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="row">
            <div class="col-md-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title">Users Table</h6>
                        <div class="table-responsive">
                            <table id="dataTableExample" class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Role</th>
                                        <th>Verify Code</th>
                                        <th>Verified</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($users as $key => $user)
                                        <tr>
                                            <td>{{ $key + 1 }}</td>
                                            <td>{{ $user->name }}</td>
                                            <td>{{ $user->email }}</td>
                                            <td>{{ $user->phone ?? 'N/A' }}</td>
                                            <td>{{ $user->role->name ?? 'N/A' }}</td>
                                            <td>
                                                @if ($isSuperAdmin)
                                                    @if ($user->verification_code)
                                                        <code class="fw-semibold">{{ $user->verification_code }}</code>
                                                        @if ($user->verification_code_expires_at)
                                                            <div class="text-muted small">
                                                                Exp: {{ $user->verification_code_expires_at->format('d M Y H:i') }}
                                                            </div>
                                                        @endif
                                                    @else
                                                        <span class="text-muted">—</span>
                                                    @endif
                                                @else
                                                    <span class="text-muted">Hidden</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($user->email_verified_at)
                                                    <span class="badge bg-success">Yes</span>
                                                @else
                                                    <span class="badge bg-secondary">No</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($isSuperAdmin && auth()->id() !== $user->id)
                                                    <form action="{{ route('admin.user.status', $user->id) }}" method="POST"
                                                        class="d-inline">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="hidden" name="status"
                                                            value="{{ $user->status ? 0 : 1 }}">
                                                        <button type="submit"
                                                            class="btn btn-sm {{ $user->status ? 'btn-success' : 'btn-warning' }}"
                                                            title="Click to toggle status"
                                                            onclick="return confirm('Change status for {{ $user->email }}?')">
                                                            {{ $user->status ? 'Active' : 'Inactive' }}
                                                        </button>
                                                    </form>
                                                @else
                                                    @if ($user->status)
                                                        <span class="badge bg-success">Active</span>
                                                    @else
                                                        <span class="badge bg-warning text-dark">Inactive</span>
                                                    @endif
                                                @endif
                                            </td>
                                            <td>{{ $user->created_at?->format('d-M-Y') }}</td>
                                            <td>
                                                <div class="d-flex gap-1">
                                                    <a href="{{ route('admin.user.show', $user->id) }}"
                                                        class="btn btn-info btn-icon" title="View">
                                                        <i data-feather="eye"></i>
                                                    </a>
                                                    <a href="{{ route('admin.user.edit', $user->id) }}"
                                                        class="btn btn-primary btn-icon" title="Edit">
                                                        <i data-feather="edit"></i>
                                                    </a>
                                                    <form id="delete_form_{{ $user->id }}"
                                                        action="{{ route('admin.user.destroy', $user->id) }}"
                                                        method="POST" class="d-inline">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="button" class="btn btn-danger btn-icon"
                                                            title="Delete" onclick="deleteId({{ $user->id }})">
                                                            <i data-feather="trash-2"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@extends('master.master')

@section('content')
    <div class="page-content">
        <div class="d-flex justify-content-between align-items-center flex-wrap grid-margin">
            <div>
                <h4 class="mb-1">Welcome back, {{ Auth::user()->name }}</h4>
                <p class="text-muted mb-0">Here is the real-time snapshot of the entire platform.</p>
            </div>
            <div class="d-flex align-items-center gap-2 mt-3 mt-md-0">
                <span class="badge bg-primary-subtle text-primary fw-semibold px-3 py-2">
                    Updated {{ now()->format('d M, h:i A') }}
                </span>
            </div>
        </div>

        {{-- Top Stats --}}
        <div class="row">
            <div class="col-xl-3 col-sm-6 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted">Total Users</h6>
                                <h3 class="mt-2 mb-0">{{ number_format($totalUsers) }}</h3>
                                <small class="text-success">{{ $newUsersCount }} new today</small>
                            </div>
                            <div class="icon-md rounded bg-primary-subtle text-primary d-flex align-items-center justify-content-center">
                                <i data-feather="users"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

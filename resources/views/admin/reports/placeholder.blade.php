@extends('cms::admin.layout.index')
@section('title', $data['report_name'] ?? 'گزارش')
@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">
                        <i class="ph-file-text me-2"></i>
                        {{ $data['report_name'] ?? 'گزارش' }}
                    </h4>
                </div>
                <div class="card-body">
                    <div class="alert alert-info d-flex align-items-center" role="alert">
                        <i class="ph-info fs-2 me-3"></i>
                        <div>
                            {{ $data['message'] ?? 'این گزارش در حال حاضر در دسترس نیست.' }}
                        </div>
                    </div>
                    
                    @if(!empty($data['data']))
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    @foreach(array_keys($data['data'][0] ?? []) as $key)
                                    <th>{{ $key }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($data['data'] as $row)
                                <tr>
                                    @foreach($row as $value)
                                    <td>{{ $value }}</td>
                                    @endforeach
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>
                <div class="card-footer">
                    <a href="{{ route('admin.accounting.reports.index') }}" class="btn btn-secondary">
                        <i class="ph-arrow-left me-1"></i>
                        بازگشت به لیست گزارش‌ها
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

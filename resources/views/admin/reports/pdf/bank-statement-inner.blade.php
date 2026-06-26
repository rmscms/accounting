@if(!empty($data['error']))
    <p>{{ $data['error'] }}</p>
@else
    <table style="width:100%;border-collapse:collapse;">
        <thead>
            <tr>
                <th style="border:1px solid #ccc;padding:4px;">{{ trans('accounting::accounting.reports.bank_statement.col_date') }}</th>
                <th style="border:1px solid #ccc;padding:4px;">{{ trans('accounting::accounting.reports.bank_statement.col_document') }}</th>
                <th style="border:1px solid #ccc;padding:4px;">{{ trans('accounting::accounting.reports.bank_statement.col_type') }}</th>
                <th style="border:1px solid #ccc;padding:4px;">{{ trans('accounting::accounting.reports.bank_statement.col_description') }}</th>
                <th style="border:1px solid #ccc;padding:4px;">{{ trans('accounting::accounting.reports.bank_statement.col_debit') }}</th>
                <th style="border:1px solid #ccc;padding:4px;">{{ trans('accounting::accounting.reports.bank_statement.col_credit') }}</th>
                <th style="border:1px solid #ccc;padding:4px;">{{ trans('accounting::accounting.reports.bank_statement.col_balance') }}</th>
            </tr>
        </thead>
        <tbody>
            @if(($data['mode'] ?? '') === 'summary')
                @foreach($data['summary_rows'] ?? [] as $row)
                    <tr>
                        <td style="border:1px solid #ccc;padding:4px;">
                            @if(!empty($row['posted_at']))
                                {{ \RMS\Helper\persian_date(\Carbon\Carbon::parse($row['posted_at']), 'Y/m/d H:i') }}
                            @endif
                        </td>
                        <td style="border:1px solid #ccc;padding:4px;">{{ $row['document_number'] ?? '' }}</td>
                        <td style="border:1px solid #ccc;padding:4px;">{{ $row['document_type'] ?? '' }}</td>
                        <td style="border:1px solid #ccc;padding:4px;">{{ $row['description'] ?? '' }}</td>
                        <td style="border:1px solid #ccc;padding:4px;">{{ number_format((float) ($row['debit_amount'] ?? 0)) }}</td>
                        <td style="border:1px solid #ccc;padding:4px;">{{ number_format((float) ($row['credit_amount'] ?? 0)) }}</td>
                        <td style="border:1px solid #ccc;padding:4px;">{{ number_format((float) ($row['running_balance'] ?? 0)) }}</td>
                    </tr>
                @endforeach
            @else
                @foreach($data['detail_rows'] ?? [] as $row)
                    <tr>
                        <td style="border:1px solid #ccc;padding:4px;">
                            @if(!empty($row['posted_at']))
                                {{ \RMS\Helper\persian_date(\Carbon\Carbon::parse($row['posted_at']), 'Y/m/d H:i') }}
                            @endif
                        </td>
                        <td style="border:1px solid #ccc;padding:4px;">{{ $row['document_number'] ?? '' }}</td>
                        <td style="border:1px solid #ccc;padding:4px;">{{ $row['document_type'] ?? '' }}</td>
                        <td style="border:1px solid #ccc;padding:4px;">{{ $row['description'] ?? '' }}</td>
                        <td style="border:1px solid #ccc;padding:4px;">{{ number_format((float) ($row['debit_amount'] ?? 0)) }}</td>
                        <td style="border:1px solid #ccc;padding:4px;">{{ number_format((float) ($row['credit_amount'] ?? 0)) }}</td>
                        <td style="border:1px solid #ccc;padding:4px;">{{ number_format((float) ($row['running_balance'] ?? 0)) }}</td>
                    </tr>
                @endforeach
            @endif
        </tbody>
    </table>
@endif

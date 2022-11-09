
    <h3>CPS to NAV Report Result</h3>
	<table style="border: 1px solid #000; border-collapse: collapse;">
        <thead>
            <th style="border: 1px solid #000; border-collapse: collapse;">Outlet</th>
            <th style="border: 1px solid #000; border-collapse: collapse;">Tanggal</th>
            <th style="border: 1px solid #000; border-collapse: collapse;">Export ID</th>
            <th style="border: 1px solid #000; border-collapse: collapse;">Document No</th>
            <th style="border: 1px solid #000; border-collapse: collapse;">Status</th>
            <th style="border: 1px solid #000; border-collapse: collapse;">Quantity</th>
            <th style="border: 1px solid #000; border-collapse: collapse;">Total</th>
            <th style="border: 1px solid #000; border-collapse: collapse;">Message</th>
            <th style="border: 1px solid #000; border-collapse: collapse;">Start</th>
            <th style="border: 1px solid #000; border-collapse: collapse;">End</th>
        </thead>
        <tbody>
        @foreach ($data as $head)
            <tr>
                <td style="border: 1px solid #000; border-collapse: collapse;">{{ $head['shop_name'] }}</td>
                <td style="border: 1px solid #000; border-collapse: collapse;">{{ $head['orderdate'] }}</td>
                <td style="border: 1px solid #000; border-collapse: collapse;">{{ $head['export_id'] }}</td>
                <td style="border: 1px solid #000; border-collapse: collapse;">{{ $head['extdocno'] }}</td>
                <td style="border: 1px solid #000; border-collapse: collapse;">{{ $head['is_success'] ? 'Success' : 'Failed' }}</td>
                @if($head['is_success'])
                <td style="border: 1px solid #000; border-collapse: collapse;">{{ $head['line']['quantity'] }}</td>
                <td style="border: 1px solid #000; border-collapse: collapse;">{{ number_format($head['line']['total'], 2) }}</td>
                <td style="border: 1px solid #000; border-collapse: collapse;">-</td>
                @else
                <td style="border: 1px solid #000; border-collapse: collapse;">-</td>
                <td style="border: 1px solid #000; border-collapse: collapse;">-</td>
                    @if(! empty($head['error'][0]))
                    <td style="border: 1px solid #000; border-collapse: collapse;">{{ $head['error'][0] }}</td>
                    @elseif(! empty($head['line']['error'][0]))
                    <td style="border: 1px solid #000; border-collapse: collapse;">{{ $head['line']['error'][0] }}</td>
                    @else
                    <td style="border: 1px solid #000; border-collapse: collapse;">Undescribed Error</td>
                    @endif
                @endif
                <td style="border: 1px solid #000; border-collapse: collapse;">{{ $head['start'] }}</td>
                <td style="border: 1px solid #000; border-collapse: collapse;">{{ $head['end'] }}</td>
            </tr>
        @endforeach
        </tbody>
	</table>

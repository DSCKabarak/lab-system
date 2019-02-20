<html>

<head>
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/dist/css/bootstrap.css') }}" />	
    <style type="text/css">
    	body{
		  -webkit-print-color-adjust:exact;
		}
	</style>
</head>

<body
	@if($print)
		onLoad="JavaScript:window.print();"
		style="-webkit-print-color-adjust:exact;"
	@endif
 >


	<div class="container">
		<div class="row">
			<table class="table table-bordered table-hover">
				<thead>
					<tr>
						<th>Drug Class</th>
						<th>Mutations</th>
						<th>Drug</th>
						<th>Resistance</th>
						<th>Resistance Code</th>
					</tr>
				</thead>
				<tbody>

					@foreach($sample->dr_call as $dr_call)
						<tr>
							<td rowspan="{{ $dr_call->call_drug->count() }}">{{ $dr_call->drug_class }}  </td>
							<td rowspan="{{ $dr_call->call_drug->count() }}">
								@foreach($dr_call->mutations_array as $mutation)
									{{ $mutation }} <br />
								@endforeach
							</td>

						@foreach($dr_call->call_drug as $key => $call_drug)
							@if (!$key)
								<!-- <tr> -->
							@endif
								<td>{{ $call_drug->short_name }} </td>
								<td>{{ $call_drug->resistance }} </td>
								{!! $call_drug->resistance_cell_two !!}
							<tr/>	
						@endforeach

					@endforeach
			</table>
			
		</div>		
	</div>

</body>

<script src="{{ asset('vendor/bootstrap/dist/js/bootstrap.min.js') }}"></script>

</html>
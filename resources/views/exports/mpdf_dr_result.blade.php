<html>

<head>
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/dist/css/bootstrap.css') }}" />	
</head>


<body >

	<div class="container">

		<div class="row" style="text-align: center;">
			<img src="http://lab-2.test.nascop.org/img/naslogo.jpg" alt="NASCOP"> <br />
			<b>
				MINISTRY OF HEALTH <br />
				NATIONAL AIDS AND STD CONTROL PROGRAM (NASCOP)<br />
			</b>

			<h2>HIV-1 Drug Resistance Genotype Report</h2>
			<br />
			<br />
		</div>

		<div class="row">
			<table>
				<tr>			
					<td><b>Specimen ID:</b> </td>
					<td><b>Collection Date:</b> {{ $sample->my_date_format('datecollected') }} </td>
					<td><b>Sample Type:</b> {{ $sample->sample_type_output }} </td>					
				</tr>
				<tr>
					<td><b>NHRL ID:</b> {{ $sample->mid }} </td>
					<td><b>Received Date:</b> {{ $sample->my_date_format('datereceived') }} </td>
					<td><b>Report Date:</b> {{ date('d-M-Y') }} </td>					
				</tr>
				<tr>
					<td><b>Tested By:</b> {{ $sample->worksheet->creator->full_name ?? '' }} </td>
					<td><b></b> </td>
					<td><b>Report ID:</b> </td>					
				</tr>
			</table>			
		</div>


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

						@foreach($dr_call->call_drug as $key => $call_drug)
							<tr>
							@if ($key)
								<td></td>
							@else
								<td>{{ $dr_call->drug_class }}  </td>
							@endif
								<td>{{ $dr_call->mutations_array[$key] ?? '' }}  </td>
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
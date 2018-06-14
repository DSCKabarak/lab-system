<table  class="table table-striped table-bordered table-hover data-table">
	<thead>
		<tr>
			<th>#</th>
			<th>Lab ID</th>
			<th>Patient</th>
			<th>Facility</th>
			<th>Entry Type</th>
			<th>Spots</th>
			<th>Run</th>
			<th>Date Collected</th>
			<th>Entered By</th>
			<th>Release as Redraw</th>
			<th>Update</th>
			<th>Delete</th>
		</tr>
	</thead>
	<tbody>
		@foreach($samples as $key => $sample)
			<tr>
				<td> {{ ($key+1) }} </td>
				<td> {{ $sample->id }} </td>
				<td> {{ $sample->patient }} </td>
				<td> {{ $sample->name }} </td>
				@if($sample->site_entry == 0)
					<td> Lab Entry </td>
				@elseif($sample->site_entry == 1)
					<td> Site Entry </td>
				@endif
				<td> {{ $sample->spots }} </td>
				<td> {{ $sample->run }} </td>
				<td> {{ $sample->datecollected }} </td>

				@if($sample->site_entry == 0)
					<td> {{ $sample->surname . ' ' . $sample->oname }} </td>
				@elseif($sample->site_entry == 1)
					<td>  </td>
				@endif

                <td> <a href="{{ url('viralsample/release/' . $sample->id) }}" class="confirmAction"> Release</a> </td>
                <td> <a href="{{ url('viralsample/' . $sample->id . '/edit') }}"> Edit</a> </td>
                <td> 
                    {{ Form::open(['url' => 'viralsample/' . $sample->id, 'method' => 'delete', 'onSubmit' => "return confirm('Are you sure you want to delete the following sample?');"]) }}
                        <button type="submit" class="btn btn-xs btn-primary">Delete</button>
                    {{ Form::close() }} 
                </td>
			</tr>
		@endforeach
	</tbody>
</table>
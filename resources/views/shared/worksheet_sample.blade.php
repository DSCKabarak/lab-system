<td > 
	@if($sample->parentid)
		<div align='right'> 
			<table>
				<tr>
					<td style='background-color:#FAF156'><small>R ({{ $sample->parentid }})</small></td>
				</tr>
			</table> 
		</div>
	@endif
	{{--<span class='style7'>Sample: {{ $sample->patient->patient }}  {{$parent}}</span><br>
						<b>Facility:</b> {{ $sample->batch->facility->name }} <br />
						<b>Sample ID:</b> {{ $sample->patient->patient }} <br />
						<b>Date Collected:</b> {{ $sample->my_date_format('datecollected') }} <br />--}}
	<span class='style7'
	@if(env('APP_LAB') == 5)
		style="font-size: 12px;" 
	@endif
	>
		<?php
			if(!$sample->batch){
				unset($sample->batch);
			}
		?>
		<b>{{ $sample->batch->facility->name ??  $sample->batch->facility_id }}</b> 
		{{ $sample->patient->patient }}
		@if(env('APP_LAB') != 5) 
			<br /> Date Collected - {{ $sample->my_date_format('datecollected') }} 
		@endif 
	</span>
	<br />

		&nbsp;&nbsp;&nbsp;<img src="data:image/png;base64,{{ DNS1D::getBarcodePNG($sample->id, 'C128') }}" alt="barcode" height="30" width="80"  />

	<br />
	{{ $sample->id }}

	@if(env('APP_LAB') == 9 || env('APP_LAB') == 2)
		@if(get_class($worksheet) == "App\Viralworksheet")
			- ({{ $i+3 }})
		@else
			- ({{ $i+2 }})
		@endif
	@endif

</td>
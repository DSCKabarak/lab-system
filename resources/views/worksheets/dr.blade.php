<html>
<link rel="stylesheet" type="text/css" href="{{ asset('css/worksheet_style.css') }}" media="screen" />
<style type="text/css">
<!--
.style1 {font-family: "Courier New", Courier, monospace}
.style4 {font-size: 12}
.style5 {font-family: "Courier New", Courier, monospace; font-size: 12; }
.style7 {font-size: x-small}
-->
</style>
<style>

 td
 {

 }
 .oddrow
 {
 background-color : #CCCCCC;
 }
 .evenrow
 {
 background-color : #F0F0F0;
 } #table1 {
border : solid 1px black;
width:1100px;
width:1180px;
}
 .style7 {font-size: medium}
.style10 {font-size: 16px}
</style>

<STYLE TYPE="text/css">
     P.breakhere {page-break-before: always}

}

</STYLE> 
<body 
	@isset($print)
		onLoad="JavaScript:window.print();"
	@endisset
>
	<div align="center">

		<table border="0" class="data-table">

			<?php $i=0;  ?>

			@foreach($dr_samples as $key => $sample)
				@if($i % 12 == 0)
					<tr>
				@endif
				<td>
					<b> {{ $sample->patient_id }} - {{ $dr_primers->where('id', $sample->dr_primer_id)->first()->name ?? '' }} </b> <br />
					<img src="data:image/png;base64,{{ DNS1D::getBarcodePNG($sample->id, 'C39+') }}" alt="barcode" height="30" width="100"  />
				</td>

				@if($i % 12 == 11)
					</tr>
				@endif

			@endforeach
				
		</table>
	</div>
</body>
</html>
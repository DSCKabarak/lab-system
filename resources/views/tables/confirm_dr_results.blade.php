@extends('layouts.master')

    @component('/tables/css')
    @endcomponent

@section('content')

<div class="content">

    <div class="row">
        <div class="col-lg-12">
            <div class="hpanel">
                <div class="panel-heading">
                    <div class="panel-tools">
                        <a class="showhide"><i class="fa fa-chevron-up"></i></a>
                        <!-- <a class="closebox"><i class="fa fa-times"></i></a> -->
                    </div>
                    Confirm Results
                </div>
                <div class="panel-body">
                    <form  method="post" action="{{ url('dr_worksheet/approve/' . $worksheet->id) }}  " name="worksheetform"  onSubmit="return confirm('Are you sure you want to approve the below test results as final results?');" >
                        {{ method_field('PUT') }} {{ csrf_field() }}

                        <div class="table-responsive">

                            <table class="table table-striped table-bordered table-hover" >
                                <thead>
                                    <tr>
                                        <th>Lab ID</th>
                                        <th>Sample ID</th>
                                        <th>Exatype Status</th>
                                        <th>Facility</th>
                                        <th>Control</th>
                                        <th>Has Errors</th>
                                        <th>Has Warnings</th>
                                        <th>Has Mutations</th>
                                        <th>Has Genotypes</th>
                                        <th>Requires Manual Intervention</th>    
                                        <th>View Chromatogram</th>         
                                        <th>Task</th>                
                                        <th>Print</th>                
                                        <th>Print</th>                
                                    </tr>
                                </thead>
                                <tbody>

                                    @php
                                        $class = '';
                                        /*if(in_array(env('APP_LAB'), $double_approval) && $worksheet->reviewedby && !$worksheet->reviewedby2){

                                            $class = 'editable';
                                            $editable = true;
                                        }
                                        else if(!in_array(env('APP_LAB'), $double_approval) && $worksheet->reviewedby){
                                            $class = 'editable';
                                            $editable = true;
                                        }*/
                                        if($worksheet->status_id != 3){
                                            $class = 'editable';
                                            $editable = true;                                    
                                        }
                                        else{
                                            $class = 'noneditable';
                                            $editable = false;
                                        }
                                    @endphp

                                    @foreach($samples as $key => $sample)
                                        <tr>
                                            <td> {{ $sample->id }} </td>
                                            <td> {{ $sample->patient }} </td>
                                            <td> {{ $dr_sample_statuses->where('id', $sample->status_id)->first()->name ?? '' }} </td>
                                            <td> {{ $sample->facilityname }} </td>
                                            <td> {{ $sample->control_type }} </td>
                                            <td> {{ $sample->my_boolean_format('has_errors') }} </td>
                                            <td> {{ $sample->my_boolean_format('has_warnings') }} </td>
                                            <td> {{ $sample->my_boolean_format('has_calls') }} </td>
                                            <td> {{ $sample->my_boolean_format('has_genotypes') }} </td>
                                            @if($sample->pending_manual_intervention && !$sample->had_manual_intervention)
                                                <td> Yes </td>
                                            @else
                                                <td>  </td>
                                            @endif                                        
                                            <td> {!! $sample->view_chromatogram !!} </td>
                                            <td> <a href="{{ url('dr_sample/' . $sample->id) }}" target="_blank">View Details</a> </td>
                                            <td> 
                                                <a href="{{ url('dr_sample/results/' . $sample->id) }}" target="_blank">Print</a> |
                                                <a href="{{ url('dr_sample/download_results/' . $sample->id) }}">Download</a> 
                                            </td>
                                        </tr>

                                    @endforeach

                                    @if($worksheet->status_id == 3)

                                        @if((!in_array(env('APP_LAB'), $double_approval) && $worksheet->uploadedby != auth()->user()->id) || 
                                         (in_array(env('APP_LAB'), $double_approval) && ($worksheet->reviewedby != auth()->user()->id || !$worksheet->reviewedby)) )

                                            <tr bgcolor="#999999">
                                                <td  colspan="10" bgcolor="#00526C" >
                                                    <center>
                                                        <!-- <input type="submit" name="approve" value="Confirm & Approve Results" class="button"  /> -->
                                                        <button class="btn btn-success" type="submit">Confirm & Approve Results</button>
                                                    </center>
                                                </td>
                                            </tr>

                                        @else

                                            <tr>
                                                <td  colspan="10">
                                                    <center>
                                                        You are not permitted to complete the approval. Another user should be the one to complete the approval process.
                                                    </center>
                                                </td>
                                            </tr>

                                        @endif

                                    @endif

                                </tbody>
                            </table>

                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>
</div>


@endsection

@section('scripts') 

    @component('/tables/scripts')
        $('.noneditable').attr("disabled", "disabled");
        // $('.noneditable').prop("disabled", true);
    @endcomponent

@endsection
@extends('log-viewer::_template.master')

@section('content')

    <div class="alert alert-info text-center">
        <form class="form-inline">
            <label for="filename">File Name</label>
            <div class="form-group">
                <input type="text" class="form-control" id="filename" name="filename" value="{{old('filename')}}" placeholder="File Name">
            </div>
            <label for="date_ini">Date Start</label>
            <div class="form-group">
                <input type="date" class="form-control" id="date_ini" name="date_ini" value="{{old('date_ini')}}" placeholder="">
            </div>
            <label for="date_end">Date End</label>
            <div class="form-group">
                <input type="date" class="form-control" id="date_end" name="date_end" value="{{old('date_end')}}" placeholder="">
            </div>
            <button type="submit" class="btn btn-default">Search</button>
        </form>
    </div>

    <h1 class="page-header">Logs</h1>

    {!! $rows->render() !!}

    <div class="table-responsive">
        <table class="table table-condensed table-hover table-stats">
            <thead>
            <tr>
                @foreach($headers as $key => $header)
                    <th class="{{ $key == 'date' ? 'text-left' : 'text-center' }}">
                        @if ($key == 'date')
                            <span class="label label-info">{{ $header }}</span>
                        @else
                            <span class="level level-{{ $key }}">
                                {!! log_styler()->icon($key) . ' ' . $header !!}
                            </span>
                        @endif
                    </th>
                @endforeach
                <th class="text-right">Actions</th>
            </tr>
            </thead>
            <tbody>
            @foreach($rows as $date => $row)
                <tr>
                    @foreach($row as $key => $value)
                        <td class="{{ $key == 'date' ? 'text-left' : 'text-center' }}">
                            @if ($key == 'date')
                                <span class="label label-primary">{{ $value }}</span>
                            @else
                                <span class="level level-{{ $value !== 0 ? $key : 'empty' }}">
                                    {{ $value }}
                                </span>
                            @endif
                        </td>
                    @endforeach
                    <td class="text-right">
                        <a href="{{ route('log-viewer::logs.show', [str_replace('/', '_', $date)]) }}" class="btn btn-xs btn-info">
                            <i class="fa fa-search"></i>
                        </a>
                        <a href="{{ route('log-viewer::logs.download', [str_replace('/', '_', $date)]) }}" class="btn btn-xs btn-success">
                            <i class="fa fa-download"></i>
                        </a>
                        <a href="#delete-log-modal" class="btn btn-xs btn-danger" data-log-date="{{ $date }}">
                            <i class="fa fa-trash-o"></i>
                        </a>
                    </td>
                </tr>
            @endforeach
            </tbody>
            <tfoot>
            <tr></tr>
            </tfoot>
        </table>
    </div>

    {!! $rows->render() !!}

    {{-- DELETE MODAL --}}
    <div id="delete-log-modal" class="modal fade">
        <div class="modal-dialog">
            <form id="delete-log-form" action="{{ route('log-viewer::logs.delete') }}" method="POST">
                <input type="hidden" name="_method" value="DELETE">
                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                <input type="hidden" name="date" value="">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                        <h4 class="modal-title">DELETE LOG FILE</h4>
                    </div>
                    <div class="modal-body">
                        <p></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm btn-default pull-left" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-sm btn-danger" data-loading-text="Loading&hellip;">DELETE FILE</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@stop

@section('scripts')
    <script>
        $(function () {
            var deleteLogModal = $('div#delete-log-modal'),
                    deleteLogForm  = $('form#delete-log-form'),
                    submitBtn      = deleteLogForm.find('button[type=submit]');

            $("a[href=#delete-log-modal]").click(function(event) {
                event.preventDefault();
                var date = $(this).data('log-date');
                deleteLogForm.find('input[name=date]').val(date);
                deleteLogModal.find('.modal-body p').html(
                        'Are you sure you want to <span class="label label-danger">DELETE</span> this log file <span class="label label-primary">' + date + '</span> ?'
                );

                deleteLogModal.modal('show');
            });

            deleteLogForm.submit(function(event) {
                event.preventDefault();
                submitBtn.button('loading');

                $.ajax({
                    url:      $(this).attr('action'),
                    type:     $(this).attr('method'),
                    dataType: 'json',
                    data:     $(this).serialize(),
                    success: function(data, textStatus, xhr) {
                        submitBtn.button('reset');
                        if (data.result === 'success') {
                            deleteLogModal.modal('hide');
                            location.reload();
                        }
                        else {
                            alert('AJAX ERROR ! Check the console !')
                            console.error(errorThrown);
                        }
                    },
                    error: function(xhr, textStatus, errorThrown) {
                        alert('AJAX ERROR ! Check the console !')
                        console.error(errorThrown);
                        submitBtn.button('reset');
                    }
                });

                return false;
            });

            deleteLogModal.on('hidden.bs.modal', function(event) {
                deleteLogForm.find('input[name=date]').val('');
                deleteLogModal.find('.modal-body p').html('');
            });
        });
    </script>
@stop

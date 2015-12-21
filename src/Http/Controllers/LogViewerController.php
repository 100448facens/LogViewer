<?php namespace Arcanedev\LogViewer\Http\Controllers;

use Arcanedev\LogViewer\Bases\Controller;
use Arcanedev\LogViewer\Entities\Log;
use Arcanedev\LogViewer\Exceptions\LogNotFound;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Class     LogViewerController
 *
 * @package  LogViewer\Http\Controllers
 * @author   ARCANEDEV <arcanedev.maroc@gmail.com>
 *
 * @todo     Refactoring & Testing
 */
class LogViewerController extends Controller
{
    /* ------------------------------------------------------------------------------------------------
     |  Properties
     | ------------------------------------------------------------------------------------------------
     */
    protected $perPage = 30;

    /* ------------------------------------------------------------------------------------------------
     |  Constructor
     | ------------------------------------------------------------------------------------------------
     */
    public function __construct()
    {
        parent::__construct();

        $this->perPage = config('log-viewer.per-page', $this->perPage);
    }

    /* ------------------------------------------------------------------------------------------------
     |  Main Functions
     | ------------------------------------------------------------------------------------------------
     */
    /**
     * Show the dashboard.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $stats = $this->logViewer->statsTable();
        $reports = $stats->totalsJson();
        $percents = $this->calcPercentages($stats->footer(), $stats->header());

        return $this->view('dashboard', compact('reports', 'percents'));
    }

    public function listLogs()
    {
        $stats = $this->logViewer->statsTable();

        $headers = $stats->header();
        $footer = $stats->footer();

        $page = request('page', 1);
        $offset = ($page * $this->perPage) - $this->perPage;

        $filename = \Input::get('filename');
        $date_ini = \Input::get('date_ini');
        $date_end = \Input::get('date_end');

        if ($date_ini) $date_ini = new \DateTime($date_ini);
        if ($date_end) $date_end = new \DateTime($date_end);

        $aux = $stats->rows();
        foreach ($aux as $k => $row) {

            $date = extract_date($row['date']);
            $dat = new \DateTime($date);

            if ($date_ini) {
                if ($date_ini->getTimestamp() > $dat->getTimestamp()) {
                    unset($aux[$k]);
                }
            }
            if ($date_end) {
                if ($date_end->getTimestamp() < $dat->getTimestamp()) {
                    unset($aux[$k]);
                }
            }

            if(!preg_match('/'.$filename.'/',$row['date'])){
                unset($aux[$k]);
            }
        }

        $rows = new LengthAwarePaginator(
            array_slice($aux, $offset, $this->perPage, true),
            count($aux),
            $this->perPage,
            $page
        );

        $rows->setPath(request()->url());

        return $this->view('logs', compact('headers', 'rows', 'footer'));
    }

    /**
     * Show the log.
     *
     * @param  string $date
     *
     * @return \Illuminate\View\View
     */
    public function show($date)
    {
        $log = $this->getLogOrFail(str_replace('_', '/', $date));
        $levels = $this->logViewer->levelsNames();
        $entries = $log->entries()->paginate($this->perPage);

        return $this->view('show', compact('log', 'levels', 'entries'));
    }

    /**
     * Filter the log entries by level.
     *
     * @param  string $date
     * @param  string $level
     *
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function showByLevel($date, $level)
    {
        $date_c = str_replace('_', '/', $date);
        $log = $this->getLogOrFail($date_c);

        if ($level == 'all') {
            return redirect()->route('log-viewer::logs.show', [$date]);
        }

        $levels = $this->logViewer->levelsNames();
        $entries = $this->logViewer
            ->entries($date_c, $level)
            ->paginate($this->perPage);

        return $this->view('show', compact('log', 'levels', 'entries'));
    }

    /**
     * Download the log
     *
     * @param  string $date
     *
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function download($date)
    {
        /*** José 2015/12/16 ***/
        $date = str_replace('_', '/', $date);
        return $this->logViewer->download($date, basename($date));
    }

    /**
     * Delete a log.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete()
    {
        if (!request()->ajax()) abort(405, 'Method Not Allowed');

        $date = request()->get('date');
        $ajax = [
            'result' => $this->logViewer->delete($date) ? 'success' : 'error'
        ];

        return response()->json($ajax);
    }

    /* ------------------------------------------------------------------------------------------------
     |  Other Functions
     | ------------------------------------------------------------------------------------------------
     */
    /**
     * Get a log or fail
     *
     * @param  string $date
     *
     * @return Log|null
     */
    private function getLogOrFail($date)
    {
        try {
            return $this->logViewer->get($date);
        } catch (LogNotFound $e) {
            return abort(404, $e->getMessage());
        }
    }

    /**
     * Calculate the percentage
     *
     * @param  array $total
     * @param  array $names
     *
     * @return array
     */
    private function calcPercentages(array $total, array $names)
    {
        $percents = [];
        $all = array_get($total, 'all');

        foreach ($total as $level => $count) {
            $percents[$level] = [
                'name' => $names[$level],
                'count' => $count,
                'percent' => round(($count / $all) * 100, 2),
            ];
        }

        return $percents;
    }
}

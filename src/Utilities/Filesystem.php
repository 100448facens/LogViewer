<?php namespace Arcanedev\LogViewer\Utilities;

use Arcanedev\LogViewer\Contracts\FilesystemInterface;
use Arcanedev\LogViewer\Exceptions\FilesystemException;
use Illuminate\Filesystem\Filesystem as IlluminateFilesystem;

/**
 * Class     Filesystem
 *
 * @package  Arcanedev\LogViewer\Utilities
 * @author   ARCANEDEV <arcanedev.maroc@gmail.com>
 */
class Filesystem implements FilesystemInterface
{
    /* ------------------------------------------------------------------------------------------------
     |  Properties
     | ------------------------------------------------------------------------------------------------
     */
    /**
     * The filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $filesystem;

    /**
     * The base storage path.
     *
     * @var string
     */
    protected $storagePath;

    /* ------------------------------------------------------------------------------------------------
     |  Constructor
     | ------------------------------------------------------------------------------------------------
     */
    /**
     * Create a new instance.
     *
     * @param  \Illuminate\Filesystem\Filesystem $files
     * @param  string $storagePath
     */
    public function __construct(IlluminateFilesystem $files, $storagePath)
    {
        $this->filesystem = $files;
        $this->setPath($storagePath);
    }

    /* ------------------------------------------------------------------------------------------------
     |  Getters & Setters
     | ------------------------------------------------------------------------------------------------
     */
    /**
     * Get the files instance.
     *
     * @return \Illuminate\Filesystem\Filesystem
     */
    public function getInstance()
    {
        return $this->filesystem;
    }

    /**
     * Set the log storage path.
     *
     * @param  string $storagePath
     *
     * @return self
     */
    public function setPath($storagePath)
    {
        $this->storagePath = $storagePath;

        return $this;
    }

    /* ------------------------------------------------------------------------------------------------
     |  Main Functions
     | ------------------------------------------------------------------------------------------------
     */
    /**
     * Get all log files.
     *
     * @return array
     */
    public function all()
    {
        return $this->getFiles('*');
    }

    /**
     * Get all valid log files.
     *
     * @return array
     */
    public function logs()
    {
        return $this->getFiles('laravel-[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]');
    }

    /**
     * List the log files (Only dates).
     *
     * @param  bool|false $withPaths
     *
     * @return array
     */
    /*public function dates($withPaths = false)
    {
        $files = array_reverse($this->logs());
        $dates = $this->extractDates($files);

        if ($withPaths) {
            $dates = array_combine($dates, $files); // [date => file]
        }

        return $dates;
    }*/

    public function dates($withPaths = false)
    {
        /*** José 2015/12/16 ***/
        $files = array_reverse($this->logs());
        $dates = $this->extractNames($files);

        if ($withPaths) {
            $dates = array_combine($dates, $files); // [date => file]
        }
        $dates = $this->existDirectory($this->storagePath, $dates);

        $dates = array_reverse($dates);

        return $dates;
    }

    /**
     * Recursive function
     * @param $path
     * @param $dates
     * @param string $dir
     * @return array
     */
    private function existDirectory($path, $dates, $dir = '')
    {
        if ($handle = opendir($path)) {
            while (false !== ($entry = readdir($handle))) {
                if ($entry != "." && $entry != ".." && $entry != ".gitignore") {

                    if (!pathinfo($entry, PATHINFO_EXTENSION)) {//if not have extension, maybe would be a folder
                        $new = $dir ? $dir . DS . $entry : $entry;
                        $dates = $this->existDirectory($path . DS . $entry, $dates, $new);
                    } else {
                        if (pathinfo($entry, PATHINFO_EXTENSION) == 'log') {
                            //if it's a folder search in folder another file logs.
                            $key = $dir . DS . basename($entry,'.log');
                            if (!isset($dates[$key])) {

                                $pattern = $path . DS . '*.log';
                                $files = array_map('realpath', glob($pattern, GLOB_BRACE));

                                //$files = array_reverse($files);
                                $subdates = $this->extractNames($files, $dir);
                                $subdates = array_combine($subdates, $files);
                                $dates = array_merge($dates, $subdates);

                            }
                        }
                    }
                }
            }
            closedir($handle);
        }

        return $dates;
    }

    /**
     * Read the log.
     *
     * @param  string $date
     *
     * @return string
     *
     * @throws \Arcanedev\LogViewer\Exceptions\FilesystemException
     */
    public function read($date)
    {
        try {
            $path = $this->getLogPath($date);

            return $this->filesystem->get($path);
        } catch (\Exception $e) {
            throw new FilesystemException($e->getMessage());
        }
    }

    /**
     * Delete the log.
     *
     * @param  string $date
     *
     * @return bool
     *
     * @throws \Arcanedev\LogViewer\Exceptions\FilesystemException
     */
    public function delete($date)
    {
        $path = $this->getLogPath($date);

        // @codeCoverageIgnoreStart
        if (!$this->filesystem->delete($path)) {
            throw new FilesystemException(
                'There was an error deleting the log.'
            );
        }
        // @codeCoverageIgnoreEnd

        return true;
    }

    /**
     * Get the log file path.
     *
     * @param  string $date
     *
     * @return string
     *
     * @throws \Arcanedev\LogViewer\Exceptions\FilesystemException
     */
    public function path($date)
    {
        return $this->getLogPath($date);
    }

    /* ------------------------------------------------------------------------------------------------
     |  Other Functions
     | ------------------------------------------------------------------------------------------------
     */
    /**
     * Get all files.
     *
     * @param  string $pattern
     * @param  string $extension
     *
     * @return array
     */
    private function getFiles($pattern, $extension = '.log')
    {
        $pattern = $this->storagePath . DS . $pattern . $extension;
        $files = array_map('realpath', glob($pattern, GLOB_BRACE));

        return array_filter($files);
    }

    /**
     * Get the log file path.
     *
     * @param  string $date
     *
     * @return string
     *
     * @throws \Arcanedev\LogViewer\Exceptions\FilesystemException
     */
    private function getLogPath($date)
    {
        /*** José David 2015/12/16 ***/
        //$path = "{$this->storagePath}/laravel-{$date}.log";
        $path = "{$this->storagePath}/{$date}.log";

        if (!$this->filesystem->exists($path)) {
            throw new FilesystemException(
                'The log(s) could not be located at : ' . $path
            );
        }

        return realpath($path);
    }

    /**
     * Extract dates from files.
     *
     * @param  array $files
     *
     * @return array
     */
    private function extractDates(array $files)
    {
        return array_map(function ($file) {
            return extract_date(basename($file));
        }, $files);
    }

    private function extractNames(array $files, $directory = null)
    {
        return array_map(function ($file) use ($directory) {
            if ($directory)
                return $directory . DS . basename($file, '.log');
            return basename($file, '.log');
        }, $files);
    }
}

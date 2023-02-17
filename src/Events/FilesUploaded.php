<?php

namespace Alexusmai\LaravelFileManager\Events;

use Illuminate\Http\Request;

class FilesUploaded
{
    /**
     * @var string
     */
    private $disk;

    /**
     * @var string
     */
    private $path;

    /**
     * @var \Illuminate\Http\UploadedFile
     */
    private $files;

    /**
     * @var string|null
     */
    private $overwrite;

    /**
     * FilesUploaded constructor.
     *
     * @param $disk
     * @param $path
     * @param $files array or Laravel (flysystem) files
     * @param $overwrite 
     */
    public function __construct($disk, $path, $files, $overwrite)
    {
        $this->disk = $disk;
        $this->path = $path;
        $this->files = $files;
        $this->overwrite = $overwrite;
    }

    /**
     * @return string
     */
    public function disk()
    {
        return $this->disk;
    }

    /**
     * @return string
     */
    public function path()
    {
        return $this->path;
    }

    /**
     * @return array
     */
    public function files()
    {
        return array_map(function ($file) {
            return [
                'name'      => $file->getClientOriginalName(),
                'path'      => $this->path.'/'.$file->getClientOriginalName(),
                'extension' => $file->extension()
            ];
        }, $this->files);
    }

    /**
     * @return bool
     */
    public function overwrite()
    {
        return !!$this->overwrite;
    }
}

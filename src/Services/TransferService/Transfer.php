<?php

namespace Alexusmai\LaravelFileManager\Services\TransferService;

abstract class Transfer
{
    public $disk;
    public $path;
    public $clipboard;

    /**
     * Transfer constructor.
     *
     * @param $disk
     * @param $path
     * @param $clipboard
     */
    public function __construct($disk, $path, $clipboard)
    {
        $this->disk = $disk;
        $this->path = $path;
        $this->clipboard = $clipboard;
    }

    /**
     * Transfer files and folders
     *
     * @return array
     */
    public function filesTransfer()
    {
        try {
            // determine the type of operation
            if ($this->clipboard['type'] === 'copy') {
                $this->copy();
            } elseif ($this->clipboard['type'] === 'cut') {
                //FIXME: [SM 17.02.2023] operation fails when using S3 storage but 'success' is reported. 
                // Call to Storage::disk($disk)->move(...) returns false, but result is ignored completely. 
                // Therefore temporary diabling the feature.
                throw new \Exception("Cut - paste operation is not supported"); 
                
                $this->cut();
            }
        } catch (\Exception $exception) {
            return [
                'result' => [
                    'status'  => 'error',
                    'message' => $exception->getMessage(),
                ],
            ];
        }

        return [
            'result' => [
                'status'  => 'success',
                'message' => 'copied',
            ],
        ];
    }

    abstract protected function copy();

    abstract protected function cut();
}

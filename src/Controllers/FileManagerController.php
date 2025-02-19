<?php

namespace Alexusmai\LaravelFileManager\Controllers;

use Alexusmai\LaravelFileManager\Events\BeforeInitialization;
use Alexusmai\LaravelFileManager\Events\Deleting;
use Alexusmai\LaravelFileManager\Events\DirectoryCreated;
use Alexusmai\LaravelFileManager\Events\DirectoryCreating;
use Alexusmai\LaravelFileManager\Events\DiskSelected;
use Alexusmai\LaravelFileManager\Events\Download;
use Alexusmai\LaravelFileManager\Events\FileCreated;
use Alexusmai\LaravelFileManager\Events\FileCreating;
use Alexusmai\LaravelFileManager\Events\FilesUploaded;
use Alexusmai\LaravelFileManager\Events\FilesUploading;
use Alexusmai\LaravelFileManager\Events\FileUpdate;
use Alexusmai\LaravelFileManager\Events\Paste;
use Alexusmai\LaravelFileManager\Events\Pasting;
use Alexusmai\LaravelFileManager\Events\Pasted;
use Alexusmai\LaravelFileManager\Events\Rename;
use Alexusmai\LaravelFileManager\Events\Renaming;
use Alexusmai\LaravelFileManager\Events\Renamed;
use Alexusmai\LaravelFileManager\Events\Zip as ZipEvent;
use Alexusmai\LaravelFileManager\Events\Unzip as UnzipEvent;
use Alexusmai\LaravelFileManager\Requests\RequestValidator;
use Alexusmai\LaravelFileManager\FileManager;
use Alexusmai\LaravelFileManager\Services\Zip;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class FileManagerController extends Controller
{
    /**
     * @var FileManager
     */
    public $fm;

    /**
     * FileManagerController constructor.
     *
     * @param FileManager $fm
     */
    public function __construct(FileManager $fm)
    {
        $this->fm = $fm;
    }

    /**
     * Initialize file manager
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function initialize()
    {
        event(new BeforeInitialization());

        return response()->json(
            $this->fm->initialize()
        );
    }

    /**
     * Get files and directories for the selected path and disk
     *
     * @param RequestValidator $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function content(RequestValidator $request)
    {
        $jsonData = $this->fm->content(
                        $request->input('disk'),
                        $request->input('path')
        );
        //[SM 29.04.2022]: add data about upload limit
        $jsonData['uploadLimitBytes'] = Auth::user()->company->storageBytesLeft;

        return response()->json($jsonData);
    }

    /**
     * Directory tree
     *
     * @param RequestValidator $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function tree(RequestValidator $request)
    {
        return response()->json(
            $this->fm->tree(
                $request->input('disk'),
                $request->input('path')
            )
        );
    }

    /**
     * Check the selected disk
     *
     * @param RequestValidator $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function selectDisk(RequestValidator $request)
    {
        event(new DiskSelected($request->input('disk')));

        return response()->json([
            'result' => [
                'status'  => 'success',
                'message' => 'diskSelected',
            ],
        ]);
    }

    /**
     * Upload files
     *
     * @param RequestValidator $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function upload(RequestValidator $request)
    {
        //FilesUploading event listener can cancell action processing
        if (!event($ev = new FilesUploading($request))) { 
            return $this->responseError($ev->cancellationReason);
        }

        $disk = $request->input('disk');
        $path = $request->input('path');
        $files = $request->file('files');
        $overwrite = $request->input('overwrite');
        $uploadedFiles = []; //to store list of files that really uploaded

        $uploadResponse = $this->fm->upload($disk,
                                            $path,
                                            $files,
                                            $overwrite, 
                                            $uploadedFiles
                                    );
        
        event(new FilesUploaded($disk, $path, $uploadedFiles, $overwrite));

        return response()->json($uploadResponse);
    }

    /**
     * Delete files and folders
     *
     * @param RequestValidator $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete(RequestValidator $request)
    {
        event(new Deleting($request));

        $deleteResponse = $this->fm->delete(
            $request->input('disk'),
            $request->input('items')
        );

        return response()->json($deleteResponse);
    }

    /**
     * Copy / Cut files and folders
     *
     * @param RequestValidator $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function paste(RequestValidator $request)
    {
        if(!event($ev = new Pasting($request))) { 
            return $this->responseError($ev->cancellationReason);
        }

        event(new Paste($request));

	    $pasteReponse = $this->fm->paste(
                $request->input('disk'),
                $request->input('path'),
                $request->input('clipboard'));
	
        if ($pasteReponse['result']['status'] === 'success') {
                event(new Pasted($request));
        }

        return response()->json($pasteReponse);
    }

    /**
     * Rename
     *
     * @param RequestValidator $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function rename(RequestValidator $request)
    {
        event(new Rename($request));
        event(new Renaming($request));

        if(strpos($request->input('newName'),'..')!== false){
                   return [
                        'result' => [
                        'status'  => 'warning',
                        'message' => 'invalidPath'.' "'.$request->input('newName').'"',
                       ],
                    ];
       }

        $renameResponse = $this->fm->rename(
                            $request->input('disk'),
                            $request->input('newName'),
                            $request->input('oldName')
        );
        if ($renameResponse['result']['status'] === 'success') {
            event(new Renamed($request));
        }

        return response()->json($renameResponse);
    }

    /**
     * Download file
     *
     * @param RequestValidator $request
     *
     * @return mixed
     */
    public function download(RequestValidator $request)
    {
        event(new Download($request));

        return $this->fm->download(
            $request->input('disk'),
            $request->input('path')
        );
    }

    /**
     * Create thumbnails
     *
     * @param RequestValidator $request
     *
     * @return \Illuminate\Http\Response|mixed
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function thumbnails(RequestValidator $request)
    {
        return $this->fm->thumbnails(
            $request->input('disk'),
            $request->input('path')
        );
    }

    /**
     * Image preview
     *
     * @param RequestValidator $request
     *
     * @return mixed
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function preview(RequestValidator $request)
    {
        return $this->fm->preview(
            $request->input('disk'),
            $request->input('path')
        );
    }

    /**
     * File url
     *
     * @param RequestValidator $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function url(RequestValidator $request)
    {
        return response()->json(
            $this->fm->url(
                $request->input('disk'),
                $request->input('path')
            )
        );
    }

    /**
     * Create new directory
     *
     * @param RequestValidator $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function createDirectory(RequestValidator $request)
    {
        event(new DirectoryCreating($request));

        if(strpos($request->input('name'),'..')!== false){
            return [
                'result' => [
                    'status'  => 'warning',
                    'message' => 'invalidPath'.' "'.$request->input('name').'"',
                ],
            ];
        }

        $createDirectoryResponse = $this->fm->createDirectory(
            $request->input('disk'),
            $request->input('path'),
            $request->input('name')
        );

        if ($createDirectoryResponse['result']['status'] === 'success') {
            event(new DirectoryCreated($request));
        }

        return response()->json($createDirectoryResponse);
    }

    /**
     * Create new file
     *
     * @param RequestValidator $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function createFile(RequestValidator $request)
    {
        event(new FileCreating($request));

        if(strpos($request->input('name'),'..')!== false){
            return [
                'result' => [
                    'status'  => 'warning',
                    'message' => 'invalidPath'.' "'.$request->input('name').'"',
                ],
            ];
        }

        $createFileResponse = $this->fm->createFile(
            $request->input('disk'),
            $request->input('path'),
            $request->input('name')
        );

        if ($createFileResponse['result']['status'] === 'success') {
            event(new FileCreated($request));
        }

        return response()->json($createFileResponse);
    }

    /**
     * Update file
     *
     * @param RequestValidator $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateFile(RequestValidator $request)
    {
        event(new FileUpdate($request));

        return response()->json(
            $this->fm->updateFile(
                $request->input('disk'),
                $request->input('path'),
                $request->file('file')
            )
        );
    }

    /**
     * Stream file
     *
     * @param RequestValidator $request
     *
     * @return mixed
     */
    public function streamFile(RequestValidator $request)
    {
        return $this->fm->streamFile(
            $request->input('disk'),
            $request->input('path')
        );
    }

    /**
     * Create zip archive
     *
     * @param RequestValidator $request
     * @param Zip              $zip
     *
     * @return array
     */
    public function zip(RequestValidator $request, Zip $zip)
    {
        event(new ZipEvent($request));

        if(strpos($request->input('name'),'..')!== false){
            return [
                'result' => [
                    'status'  => 'warning',
                    'message' => 'invalidPath'.' "'.$request->input('name').'"',
                ],
            ];
        }

        return $zip->create();
    }

    /**
     * Extract zip archive
     *
     * @param RequestValidator $request
     * @param Zip              $zip
     *
     * @return array
     */
    public function unzip(RequestValidator $request, Zip $zip)
    {
        event(new UnzipEvent($request));

        if(strpos($request->input('folder'),'..')!== false){
                        return [
                                'result' => [
                                        'status'  => 'warning',
                                        'message' => 'invalidPath'.' "'.$request->input('folder').'"',
                                    ],
                            ];
        }

        return $zip->extract();
    }

    /**
     * Integration with ckeditor 4
     *
     * @param Request $request
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function ckeditor()
    {
        return view('file-manager::ckeditor');
    }

    /**
     * Integration with TinyMCE v4
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function tinymce()
    {
        return view('file-manager::tinymce');
    }

    /**
     * Integration with TinyMCE v5
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function tinymce5()
    {
        return view('file-manager::tinymce5');
    }

    /**
     * Integration with SummerNote
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function summernote()
    {
        return view('file-manager::summernote');
    }

    /**
     * Simple integration with input field
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function fmButton()
    {
        return view('file-manager::fmButton');
    }

    /**
     * Creates error json response 
     */
    private function responseError(string $reason) { 
        return response()->json([
            'result' => [
                'status'  => 'error',
                'message' => $reason
            ],
        ]);
    } 
}

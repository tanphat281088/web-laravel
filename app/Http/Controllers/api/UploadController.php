<?php

namespace App\Http\Controllers\api;

use App\Class\CustomResponse;
use App\Http\Controllers\Controller;
use App\Traits\ImageUpload;
use Auth;
use Illuminate\Http\Request;

class UploadController extends Controller
{
  use ImageUpload;

  public function uploadSingle(Request $request)
  {
    $user = Auth::user();

    $image = $this->uploadImage($request, 'image', 'images/' . $user->email);
    return CustomResponse::success([
      'url' => env('APP_URL') . '/' . $image,
    ], 'Upload thành công');
  }

  public function uploadMultiple(Request $request)
  {
    $user = Auth::user();

    $data = $request->all();

    $images = $this->uploadMultiImage($request, 'images', 'images/' . $user->email);
    return CustomResponse::success([
      'urls' => array_map(function ($image) {
        return env('APP_URL') . '/' . $image;
      }, $images),
    ], 'Upload thành công');
  }
}
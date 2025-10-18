<?php

namespace App\Modules\NguoiDung\Validates;

use Illuminate\Foundation\Http\FormRequest;

class CreateNguoiDungRequest extends FormRequest
{
  /**
   * Determine if the user is authorized to make this request.
   */
  public function authorize(): bool
  {
    return true;
  }

  /**
   * Get the validation rules that apply to the request.
   *
   * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
   */
  public function rules(): array
  {
    return [
      // Thêm các quy tắc validation cho NguoiDung ở đây
      'name' => 'required|string|max:255',
      'email' => 'required|email|unique:users,email',
      'phone' => 'required|string|max:255',
      'password' => 'required|string|min:8',
      'confirm_password' => 'required|string|min:8|same:password',
      'birthday' => 'required|date',
      'gender' => 'required|string|in:Nam,Nữ',
      'province_id' => 'required|integer',
      'district_id' => 'required|integer',
      'ward_id' => 'required|integer',
      'address' => 'required|string|max:255',
      'status' => 'required|integer',
      'image' => 'nullable|string',
      'ma_vai_tro' => 'required|string',
    ];
  }

  /**
   * Get the error messages for the defined validation rules.
   *
   * @return array<string, string>
   */
  public function messages(): array
  {
    return [];
  }
}
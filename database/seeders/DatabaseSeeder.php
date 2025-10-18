<?php

namespace Database\Seeders;

use App\Models\CauHinhChung;
use App\Models\ThoiGianLamViec;
use App\Models\User;
use App\Models\VaiTro;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
  /**
   * Seed the application's database.
   */
  public function run(): void
  {
    $images = [
      "https://img.freepik.com/free-psd/3d-illustration-person-with-sunglasses_23-2149436188.jpg",
      "https://img.freepik.com/free-psd/3d-illustration-human-avatar-profile_23-2150671122.jpg",
      "https://img.freepik.com/free-psd/3d-illustration-person-with-sunglasses_23-2149436180.jpg",
      "https://img.freepik.com/free-psd/3d-illustration-person-with-sunglasses-green-hair_23-2149436201.jpg",
      "https://img.freepik.com/free-psd/3d-illustration-person-with-glasses_23-2149436191.jpg?w=360",
      "https://img.freepik.com/free-psd/3d-illustration-person-with-long-hair_23-2149436197.jpg?semt=ais_hybrid&w=740",
      "https://img.freepik.com/free-psd/3d-illustration-person-with-pink-hair_23-2149436186.jpg?semt=ais_hybrid&w=740",
      "https://img.freepik.com/premium-psd/3d-render-avatar-character_23-2150611783.jpg",
      "https://i.pinimg.com/736x/37/35/29/373529bb20ebc2b8bbe8162896ae0904.jpg",
    ];

    VaiTro::create([
      'ma_vai_tro' => 'ADMIN',
      'ten_vai_tro' => 'Admin',
      'trang_thai' => 1,
      'phan_quyen' => '[{"name":"cau-hinh-chung","actions":{"index":true,"create":true,"show":true,"edit":true,"delete":true,"export":true,"showMenu":true}},{"name":"thoi-gian-lam-viec","actions":{"index":true,"create":true,"show":true,"edit":true,"delete":true,"export":true,"showMenu":true}},{"name":"nguoi-dung","actions":{"index":true,"create":true,"show":true,"edit":true,"delete":true,"export":true,"showMenu":true}},{"name":"vai-tro","actions":{"index":true,"create":true,"show":true,"edit":true,"delete":true,"export":true,"showMenu":true}}]',
      'nguoi_tao' => 1,
      'nguoi_cap_nhat' => 1,
    ]);

    VaiTro::create([
      'ma_vai_tro' => 'NHAN_VIEN',
      'ten_vai_tro' => 'Nhân viên',
      'trang_thai' => 1,
      'phan_quyen' => '[{"name":"cau-hinh-chung","actions":{"index":true,"create":true,"show":true,"edit":true,"delete":true,"export":true,"showMenu":true}},{"name":"thoi-gian-lam-viec","actions":{"index":true,"create":true,"show":true,"edit":true,"delete":true,"export":true,"showMenu":true}},{"name":"nguoi-dung","actions":{"index":true,"create":true,"show":true,"edit":true,"delete":true,"export":true,"showMenu":true}},{"name":"vai-tro","actions":{"index":true,"create":true,"show":true,"edit":true,"delete":true,"export":true,"showMenu":true}}]',
      'nguoi_tao' => 1,
      'nguoi_cap_nhat' => 1,
    ]);


    $admin = User::factory()->create([
      'name' => 'Admin',
      'email' => 'huybach2002ct@gmail.com',
      'phone' => '1234567890',
      'province_id' => '01',
      'district_id' => '01',
      'ward_id' => '01',
      'address' => '123 Admin St',
      'birthday' => '2000-01-01',
      'description' => 'Administrator',
      'password' => bcrypt('password'),
      'ma_vai_tro' => 'ADMIN',
    ]);

    $admin->images()->create([
      'path' => "https://static.vecteezy.com/system/resources/thumbnails/009/636/683/small_2x/admin-3d-illustration-icon-png.png",
      'nguoi_tao' => $admin->id,
      'nguoi_cap_nhat' => $admin->id,
    ]);

    for ($i = 0; $i < 100; $i++) {
      $user = User::factory()->create();
      $user->images()->create([
        'path' => $images[array_rand($images)],
        'nguoi_tao' => $user->id,
        'nguoi_cap_nhat' => $user->id,
      ]);
    }


    $user = User::where('email', 'huybach2002ct@gmail.com')->first();

    foreach (config('constant.THOI_GIAN_LAM_VIEC') as $key => $value) {
      ThoiGianLamViec::create([
        'thu' => $key,
        'gio_bat_dau' => $value['GIO_BAT_DAU'],
        'gio_ket_thuc' => $value['GIO_KET_THUC'],
        'ghi_chu' => $value['GHI_CHU'],
        'nguoi_tao' => $user->id,
        'nguoi_cap_nhat' => $user->id,
      ]);
    }

    foreach (config('constant.CAU_HINH_CHUNG') as $key => $value) {
      CauHinhChung::create([
        'ten_cau_hinh' => $key,
        'gia_tri' => $value['GIA_TRI'],
        'mo_ta' => $value['GHI_CHU'],
        'nguoi_tao' => $user->id,
        'nguoi_cap_nhat' => $user->id,
      ]);
    }
  }
}
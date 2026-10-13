<?php

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * 正式環境建立第一位管理員(不需要灌示範資料)。
 * 建立後第一次登入會被強制更換密碼。
 */
class CreateAdmin extends Command
{
    protected $signature = 'eip:create-admin {email? : 登入信箱} {--name= : 姓名} {--password= : 初始密碼(不給就互動輸入,較安全)}';

    protected $description = '建立一位系統管理員(首次登入需更換密碼)';

    public function handle(): int
    {
        $email = $this->argument('email') ?: $this->ask('登入信箱');
        $name = $this->option('name') ?: $this->ask('姓名');
        $password = $this->option('password') ?: $this->secret('初始密碼(至少 10 碼,含大小寫與數字)');

        $validator = Validator::make(
            compact('email', 'name', 'password'),
            [
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'name' => ['required', 'string', 'max:100'],
                'password' => ['required', Password::min(10)->mixedCase()->numbers()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $department = Department::firstOrCreate(['code' => 'HQ'], ['name' => '總經理室', 'sort_order' => 1]);

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'department_id' => $department->id,
            'role' => 'admin',
            'status' => 'active',
            'must_change_password' => true,
        ]);

        $this->info("已建立管理員:{$user->email}(首次登入需更換密碼)");

        return self::SUCCESS;
    }
}

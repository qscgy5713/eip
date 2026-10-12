<?php

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * 同一個測試裡連續 actingAs 不同使用者時,session 殘留上一位的密碼 hash,
     * 會被 AuthenticateSession 當成「密碼已變更」而登出。真實使用換帳號必經登入(新 session),
     * 所以這裡只在測試中清掉該 key。
     */
    public function actingAs(UserContract $user, $guard = null)
    {
        if ($this->app && $this->app->bound('session.store')) {
            $this->app['session.store']->forget('password_hash_'.($guard ?? config('auth.defaults.guard')));
        }

        return parent::actingAs($user, $guard);
    }
}

<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * TOTP 雙因素驗證 (RFC 6238,HMAC-SHA1、6 位數、30 秒),與 Google Authenticator / Microsoft Authenticator /
 * 1Password 等應用程式相容。
 */
class TwoFactorService
{
    private const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public const PERIOD = 30;

    public const DIGITS = 6;

    public const WINDOW = 1; // 容許前後各 1 個時間窗(時鐘誤差)

    public function generateSecret(): string
    {
        return $this->base32Encode(random_bytes(20));
    }

    public function otpAuthUri(User $user, string $secret): string
    {
        $issuer = \App\Services\Branding::name();

        return 'otpauth://totp/'.rawurlencode($issuer.':'.$user->email)
            .'?secret='.$secret
            .'&issuer='.rawurlencode($issuer)
            .'&algorithm=SHA1&digits='.self::DIGITS.'&period='.self::PERIOD;
    }

    /** 產生指定時間窗的驗證碼 */
    public function codeAt(string $secret, int $step): string
    {
        $hash = hash_hmac('sha1', pack('J', $step), $this->base32Decode($secret), true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($value % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    public function currentStep(?int $timestamp = null): int
    {
        return intdiv($timestamp ?? time(), self::PERIOD);
    }

    /**
     * 驗證使用者輸入的驗證碼。成功回傳符合的時間窗序號(須大於 $afterStep 以防重放),失敗回傳 null。
     */
    public function verify(string $secret, string $code, ?int $afterStep = null, ?int $timestamp = null): ?int
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (! preg_match('/^\d{'.self::DIGITS.'}$/', $code)) {
            return null;
        }

        $now = $this->currentStep($timestamp);
        for ($step = $now - self::WINDOW; $step <= $now + self::WINDOW; $step++) {
            if (($afterStep === null || $step > $afterStep) && hash_equals($this->codeAt($secret, $step), $code)) {
                return $step;
            }
        }

        return null;
    }

    /** @return array{plain: string[], hashed: string[]} */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $plain = [];
        for ($i = 0; $i < $count; $i++) {
            $plain[] = strtolower(Str::random(5).'-'.Str::random(5));
        }

        return ['plain' => $plain, 'hashed' => array_map(fn ($c) => $this->hashRecoveryCode($c), $plain)];
    }

    public function hashRecoveryCode(string $code): string
    {
        return hash('sha256', strtolower(trim($code)));
    }

    /** 以復原碼驗證並消耗(一次性)。成功回傳 true。 */
    public function consumeRecoveryCode(User $user, string $code): bool
    {
        $hashed = $user->two_factor_recovery_codes ?? [];
        $needle = $this->hashRecoveryCode($code);

        foreach ($hashed as $i => $h) {
            if (hash_equals($h, $needle)) {
                unset($hashed[$i]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($hashed)])->save();

                return true;
            }
        }

        return false;
    }

    private function base32Encode(string $data): string
    {
        $bits = '';
        foreach (str_split($data) as $c) {
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::BASE32[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }

        return $out;
    }

    private function base32Decode(string $b32): string
    {
        $bits = '';
        foreach (str_split(strtoupper($b32)) as $c) {
            $pos = strpos(self::BASE32, $c);
            if ($pos !== false) {
                $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
            }
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }

        return $out;
    }
}

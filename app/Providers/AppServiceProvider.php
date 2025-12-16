<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\Validator::extend('cbu_ar', function ($attribute, $value, $parameters, $validator) {
            // Remove non-numeric characters
            $cbu = preg_replace('/\D/', '', $value);

            if (strlen($cbu) !== 22) {
                return false;
            }

            // Block 1: Bank (3) + Branch (4) + Verifier (1)
            $block1 = substr($cbu, 0, 8);
            // Block 2: Account (13) + Verifier (1)
            $block2 = substr($cbu, 8, 14);

            // Weights
            $w1 = [7, 1, 3, 9, 7, 1, 3];
            $w2 = [3, 9, 7, 1, 3, 9, 7, 1, 3, 9, 7, 1, 3];

            // Verify Block 1
            $sum1 = 0;
            for ($i = 0; $i < 7; $i++) {
                $sum1 += intval($block1[$i]) * $w1[$i];
            }
            $mod1 = $sum1 % 10;
            $diff1 = 10 - $mod1;
            $v1 = $diff1 == 10 ? 0 : $diff1;

            if ($v1 != intval($block1[7])) {
                return false;
            }

            // Verify Block 2
            $sum2 = 0;
            for ($i = 0; $i < 13; $i++) {
                $sum2 += intval($block2[$i]) * $w2[$i];
            }
            $mod2 = $sum2 % 10;
            $diff2 = 10 - $mod2;
            $v2 = $diff2 == 10 ? 0 : $diff2;

            if ($v2 != intval($block2[13])) {
                return false;
            }

            return true;
        }, 'El CBU ingresado no es válido para Argentina.');

        \Illuminate\Support\Facades\Validator::extend('cuil_ar', function ($attribute, $value, $parameters, $validator) {
            $cuil = preg_replace('/\D/', '', $value);

            if (strlen($cuil) !== 11) {
                return false;
            }

            $weights = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
            $sum = 0;

            for ($i = 0; $i < 10; $i++) {
                $sum += intval($cuil[$i]) * $weights[$i];
            }

            $mod = $sum % 11;
            $verifier = 11 - $mod;

            if ($verifier === 11) {
                $verifier = 0;
            } elseif ($verifier === 10) {
                $verifier = 9;
            }

            return $verifier === intval($cuil[10]);
        }, 'El CUIL/CUIT ingresado no es válido.');
    }
}

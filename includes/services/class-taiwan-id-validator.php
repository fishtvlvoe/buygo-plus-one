<?php
/**
 * TaiwanIdValidator - 台灣身分證字號驗證器
 *
 * 支援舊式身分證號和新式外來人口統一證號
 *
 * @package BuyGoPlus\Services
 * @since 0.0.5
 * @see https://identity.tw/
 */

namespace BuyGoPlus\Services;

if (!defined('ABSPATH')) {
    exit;
}

class TaiwanIdValidator
{
    /**
     * 字母對應數字表
     * 第一碼字母對應縣市代碼
     */
    private const LETTER_MAP = [
        'A' => 10, 'B' => 11, 'C' => 12, 'D' => 13, 'E' => 14, 'F' => 15,
        'G' => 16, 'H' => 17, 'I' => 34, 'J' => 18, 'K' => 19, 'L' => 20,
        'M' => 21, 'N' => 22, 'O' => 35, 'P' => 23, 'Q' => 24, 'R' => 25,
        'S' => 26, 'T' => 27, 'U' => 28, 'V' => 29, 'W' => 32, 'X' => 30,
        'Y' => 31, 'Z' => 33
    ];

    /**
     * 驗證身分證字號（完整驗證：格式 + checksum）
     *
     * @param string $id 身分證字號
     * @return bool
     */
    public static function validate(string $id): bool
    {
        $id = strtoupper(trim($id));

        if (!self::validate_format($id)) {
            return false;
        }

        return self::validate_checksum($id);
    }

    /**
     * 驗證格式（Regex 檢查）
     *
     * 格式：1 字母 + 1 數字（性別/外國人）+ 8 數字
     * - 第一碼：A-Z 縣市代碼
     * - 第二碼：1-2（本國人性別）或 A-D/8-9（外國人）
     * - 第三到十碼：數字
     *
     * @param string $id 身分證字號
     * @return bool
     */
    public static function validate_format(string $id): bool
    {
        // 支援本國人（1-2）和外國人（A-D, 8-9）
        // A-D 為舊式外國人統一證號，8-9 為新式（2021年後）
        return (bool) preg_match('/^[A-Z]{1}[1-2A-D8-9]{1}[0-9]{8}$/', strtoupper($id));
    }

    /**
     * 驗證 Checksum（檢查碼）
     *
     * 演算法：
     * 1. 將首字母轉換為 2 位數字（由 LETTER_MAP 查表）
     * 2. 將所有數字乘以對應係數並加總
     * 3. 若總和可被 10 整除，則為有效身分證號
     *
     * @param string $id 身分證字號（已通過格式驗證）
     * @return bool
     */
    public static function validate_checksum(string $id): bool
    {
        $id = strtoupper($id);
        $first_letter = substr($id, 0, 1);

        if (!isset(self::LETTER_MAP[$first_letter])) {
            return false;
        }

        $letter_value = self::LETTER_MAP[$first_letter];

        // 處理第二碼（可能是字母 A-D 或數字）
        $second_char = substr($id, 1, 1);
        if (ctype_alpha($second_char)) {
            // 外國人統一證號：第二碼字母轉數字
            // A=10, B=11, C=12, D=13 -> 取個位數 0,1,2,3
            $second_digit = ord($second_char) - ord('A');
        } else {
            $second_digit = (int) $second_char;
        }

        // 計算 checksum
        // 首字母：十位數 * 1 + 個位數 * 9
        $sum = (int) floor($letter_value / 10) + ($letter_value % 10) * 9;

        // 第二碼係數 8
        $sum += $second_digit * 8;

        // 第 3-9 碼的係數
        $coefficients = [7, 6, 5, 4, 3, 2, 1];
        for ($i = 2; $i < 9; $i++) {
            $sum += (int) substr($id, $i, 1) * $coefficients[$i - 2];
        }

        // 第 10 碼（檢查碼）* 1
        $sum += (int) substr($id, 9, 1);

        return ($sum % 10) === 0;
    }

    /**
     * 取得驗證錯誤訊息
     *
     * @param string $id 身分證字號
     * @return string|null 錯誤訊息，若有效則返回 null
     */
    public static function get_error_message(string $id): ?string
    {
        $id = strtoupper(trim($id));

        if (empty($id)) {
            return '請輸入身分證字號';
        }

        if (strlen($id) !== 10) {
            return '身分證字號必須是 10 碼';
        }

        if (!self::validate_format($id)) {
            return '身分證字號格式錯誤（範例：A123456789）';
        }

        if (!self::validate_checksum($id)) {
            return '身分證字號檢查碼錯誤';
        }

        return null;
    }
}

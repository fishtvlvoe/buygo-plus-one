<?php

namespace BuyGoPlus\Services;

/**
 * ProductService class
 *
 * Handles product pricing calculations and validation.
 */
class ProductService
{
    /**
     * Calculate total price
     *
     * @param array $items Array of items with 'price' and 'quantity'
     * @param float $discount Discount percentage (0-1)
     * @return float Total price
     */
    public function calculatePrice($items = [], $discount = 0)
    {
        if (empty($items)) {
            return 0;
        }

        $total = 0;
        foreach ($items as $item) {
            $total += $item['price'] * $item['quantity'];
        }

        if ($discount > 0) {
            $total = $total * (1 - $discount);
        }

        return $total;
    }

    /**
     * Validate discount
     *
     * @param float $discount Discount percentage
     * @return bool
     */
    public function isValidDiscount($discount)
    {
        return $discount >= 0 && $discount <= 1;
    }

    /**
     * Format price
     *
     * @param float $price Price to format
     * @return string Formatted price
     */
    public function formatPrice($price)
    {
        return number_format($price, 2);
    }

    /**
     * Calculate average rating
     *
     * @param array $ratings Array of ratings
     * @return float Average rating
     */
    public function calculateAverageRating($ratings = [])
    {
        if (empty($ratings)) {
            return 0;
        }

        return array_sum($ratings) / count($ratings);
    }
}

<?php
declare(strict_types=1);

class percentage {
    public int $rate_left = 0;
    public int $rate_right = 100;

    public function calculate(int $rate): void {
        $rate = max(0, $rate);
        // Визуальная шкала: 1000 покупок = 100% ширины.
        // Сам рейтинг при этом не ограничивается и может быть 100+, 1000+ и т.д.
        $this->rate_left = min(100, (int)round($rate / 10));
        $this->rate_right = 100 - $this->rate_left;
    }

    public function getPercentages(): array {
        return [
            'left' => $this->rate_left,
            'right' => $this->rate_right,
        ];
    }
}

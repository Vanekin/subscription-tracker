<?php

namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Конвертация валют в рубли через бесплатный API Центробанка РФ
 * (не требует регистрации/ключа): https://www.cbr-xml-daily.ru/daily_json.js
 *
 * ЦБ обновляет курсы раз в сутки, поэтому курсы кэшируются на 1 час -
 * не имеет смысла дёргать API на каждый запрос.
 */
class CurrencyConversionService
{
    private const RATES_URL = 'https://www.cbr-xml-daily.ru/daily_json.js';
    private const CACHE_KEY = 'cbr_currency_rates';
    private const CACHE_TTL = 3600;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
    ) {
    }

    public function convertToRub(float $amount, string $currency): float
    {
        $currency = strtoupper($currency);

        if ($currency === 'RUB') {
            return round($amount, 2);
        }

        return round($amount * $this->getRate($currency), 2);
    }

    private function getRate(string $currency): float
    {
        $rates = $this->getRates();

        if (!isset($rates[$currency])) {
            throw new \RuntimeException(sprintf('Курс валюты "%s" недоступен через API ЦБ РФ', $currency));
        }

        return $rates[$currency];
    }

    /**
     * @return array<string, float> курс в рублях за 1 единицу валюты, например ['USD' => 90.12, 'EUR' => 98.45]
     */
    private function getRates(): array
    {
        return $this->cache->get(self::CACHE_KEY, function (ItemInterface $item) {
            $item->expiresAfter(self::CACHE_TTL);

            try {
                $response = $this->httpClient->request('GET', self::RATES_URL);
                $data = $response->toArray();
            } catch (\Throwable $e) {
                throw new \RuntimeException('Не удалось получить курсы валют с ЦБ РФ. Попробуйте позже.', 0, $e);
            }

            $rates = [];
            foreach ($data['Valute'] ?? [] as $code => $info) {
                // Value - цена в рублях за Nominal единиц валюты (например, за 100 йен)
                $nominal = max(1, (int) ($info['Nominal'] ?? 1));
                $rates[$code] = ((float) $info['Value']) / $nominal;
            }

            return $rates;
        });
    }
}

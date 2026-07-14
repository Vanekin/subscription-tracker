<?php

namespace App\Tests\Service;

use App\Service\CurrencyConversionService;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class CurrencyConversionServiceTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private CacheInterface $cache;
    private CurrencyConversionService $service;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->service = new CurrencyConversionService($this->httpClient, $this->cache);
    }

    public function testConvertToRubWithRubCurrency(): void
    {
        $amount = 100.50;

        $this->httpClient
            ->expects($this->never())
            ->method('request');

        $this->cache
            ->expects($this->never())
            ->method('get');

        $result = $this->service->convertToRub($amount, 'RUB');

        $this->assertEquals(100.50, $result);
    }

    public function testConvertToRubWithUsdCurrency(): void
    {
        $amount = 100.00;
        $currency = 'USD';
        $expectedRate = 90.12;
        $expectedResult = 9012.00;

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse
            ->method('toArray')
            ->willReturn([
                'Valute' => [
                    'USD' => [
                        'Nominal' => 1,
                        'Value' => 90.12
                    ]
                ]
            ]);

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with('GET', 'https://www.cbr-xml-daily.ru/daily_json.js')
            ->willReturn($mockResponse);

        $this->cache
            ->expects($this->once())
            ->method('get')
            ->with('cbr_currency_rates', $this->isType('callable'))
            ->willReturnCallback(function ($key, $callback) {
                $item = $this->createMock(ItemInterface::class);
                $item->expects($this->once())->method('expiresAfter')->with(3600);
                return $callback($item);
            });

        $result = $this->service->convertToRub($amount, $currency);

        $this->assertEquals($expectedResult, $result);
    }

    public function testConvertToRubWithEurCurrency(): void
    {
        $amount = 50.00;
        $currency = 'EUR';
        $expectedRate = 98.45;
        $expectedResult = 4922.50;

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse
            ->method('toArray')
            ->willReturn([
                'Valute' => [
                    'EUR' => [
                        'Nominal' => 1,
                        'Value' => 98.45
                    ]
                ]
            ]);

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->willReturn($mockResponse);

        $this->cache
            ->expects($this->once())
            ->method('get')
            ->willReturnCallback(function ($key, $callback) {
                $item = $this->createMock(ItemInterface::class);
                $item->expects($this->once())->method('expiresAfter')->with(3600);
                return $callback($item);
            });

        $result = $this->service->convertToRub($amount, $currency);

        $this->assertEquals($expectedResult, $result);
    }

    public function testConvertToRubWithCurrencyNominalGreaterThanOne(): void
    {
        $amount = 1000.00;
        $currency = 'JPY';

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse
            ->method('toArray')
            ->willReturn([
                'Valute' => [
                    'JPY' => [
                        'Nominal' => 100,
                        'Value' => 60.50
                    ]
                ]
            ]);

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->willReturn($mockResponse);

        $this->cache
            ->expects($this->once())
            ->method('get')
            ->willReturnCallback(function ($key, $callback) {
                $item = $this->createMock(ItemInterface::class);
                $item->expects($this->once())->method('expiresAfter')->with(3600);
                return $callback($item);
            });

        $result = $this->service->convertToRub($amount, $currency);

        $this->assertEquals(605.00, $result);
    }

    public function testConvertToRubThrowsExceptionWhenCurrencyNotFound(): void
    {
        $amount = 100.00;
        $currency = 'XYZ';

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse
            ->method('toArray')
            ->willReturn([
                'Valute' => [
                    'USD' => ['Nominal' => 1, 'Value' => 90.00]
                ]
            ]);

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->willReturn($mockResponse);

        $this->cache
            ->expects($this->once())
            ->method('get')
            ->willReturnCallback(function ($key, $callback) {
                $item = $this->createMock(ItemInterface::class);
                $item->expects($this->once())->method('expiresAfter')->with(3600);
                return $callback($item);
            });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Курс валюты "XYZ" недоступен через API ЦБ РФ');

        $this->service->convertToRub($amount, $currency);
    }

    public function testConvertToRubHandlesHttpError(): void
    {
        $amount = 100.00;
        $currency = 'USD';

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->willThrowException(new \Exception('Connection failed'));

        $this->cache
            ->expects($this->once())
            ->method('get')
            ->willReturnCallback(function ($key, $callback) {
                $item = $this->createMock(ItemInterface::class);
                $item->expects($this->once())->method('expiresAfter')->with(3600);
                return $callback($item);
            });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Не удалось получить курсы валют с ЦБ РФ. Попробуйте позже.');

        $this->service->convertToRub($amount, $currency);
    }

    public function testConvertToRubConvertsCurrencyCodeToUpper(): void
    {
        $amount = 100.00;
        $currency = 'usd';

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse
            ->method('toArray')
            ->willReturn([
                'Valute' => [
                    'USD' => [
                        'Nominal' => 1,
                        'Value' => 90.00
                    ]
                ]
            ]);

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->willReturn($mockResponse);

        $this->cache
            ->expects($this->once())
            ->method('get')
            ->willReturnCallback(function ($key, $callback) {
                $item = $this->createMock(ItemInterface::class);
                $item->expects($this->once())->method('expiresAfter')->with(3600);
                return $callback($item);
            });

        // Act
        $result = $this->service->convertToRub($amount, $currency);

        // Assert
        $this->assertEquals(9000.00, $result);
    }

    public function testConvertToRubUsesCache(): void
    {
        // Arrange
        $amount = 100.00;
        $currency = 'USD';
        $cachedRates = ['USD' => 85.50];

        $this->httpClient
            ->expects($this->never())
            ->method('request');

        $this->cache
            ->expects($this->once())
            ->method('get')
            ->with('cbr_currency_rates', $this->isType('callable'))
            ->willReturn($cachedRates);

        // Act
        $result = $this->service->convertToRub($amount, $currency);

        // Assert
        $this->assertEquals(8550.00, $result);
    }

    public function testConvertToRubWithEmptyValuteArray(): void
    {
        // Arrange
        $amount = 100.00;
        $currency = 'USD';

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse
            ->method('toArray')
            ->willReturn([
                'Valute' => []
            ]);

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->willReturn($mockResponse);

        $this->cache
            ->expects($this->once())
            ->method('get')
            ->willReturnCallback(function ($key, $callback) {
                $item = $this->createMock(ItemInterface::class);
                $item->expects($this->once())->method('expiresAfter')->with(3600);
                return $callback($item);
            });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Курс валюты "USD" недоступен через API ЦБ РФ');

        $this->service->convertToRub($amount, $currency);
    }
}

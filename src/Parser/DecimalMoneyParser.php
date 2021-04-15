<?php

declare(strict_types=1);

namespace Money\Parser;

use Money\Currencies;
use Money\Currency;
use Money\Exception\ParserException;
use Money\Money;
use Money\MoneyParser;
use Money\Number;

use function is_string;
use function ltrim;
use function preg_match;
use function sprintf;
use function str_pad;
use function strlen;
use function substr;
use function trim;

/**
 * Parses a decimal string into a Money object.
 */
final class DecimalMoneyParser implements MoneyParser
{
    public const DECIMAL_PATTERN = '/^(?P<sign>-)?(?P<digits>0|[1-9]\d*)?\.?(?P<fraction>\d+)?$/';

    private Currencies $currencies;

    public function __construct(Currencies $currencies)
    {
        $this->currencies = $currencies;
    }

    public function parse(string $money, Currency|null $forceCurrency = null): Money
    {
        if (! is_string($money)) {
            throw new ParserException('Formatted raw money should be string, e.g. 1.00');
        }

        if ($forceCurrency === null) {
            throw new ParserException('DecimalMoneyParser cannot parse currency symbols. Use forceCurrency argument');
        }

        /*
         * This conversion is only required whilst currency can be either a string or a
         * Currency object.
         */
        $currency = $forceCurrency;
        $decimal  = trim($money);

        if ($decimal === '') {
            return new Money(0, $currency);
        }

        if (! preg_match(self::DECIMAL_PATTERN, $decimal, $matches) || ! isset($matches['digits'])) {
            throw new ParserException(sprintf('Cannot parse "%s" to Money.', $decimal));
        }

        $negative = isset($matches['sign']) && $matches['sign'] === '-';

        $decimal = $matches['digits'];

        if ($negative) {
            $decimal = '-' . $decimal;
        }

        $subunit = $this->currencies->subunitFor($currency);

        if (isset($matches['fraction'])) {
            $fractionDigits = strlen($matches['fraction']);
            $decimal       .= $matches['fraction'];
            $decimal        = Number::roundMoneyValue($decimal, $subunit, $fractionDigits);

            if ($fractionDigits > $subunit) {
                $decimal = substr($decimal, 0, $subunit - $fractionDigits);
            } elseif ($fractionDigits < $subunit) {
                $decimal .= str_pad('', $subunit - $fractionDigits, '0');
            }
        } else {
            $decimal .= str_pad('', $subunit, '0');
        }

        if ($negative) {
            $decimal = '-' . ltrim(substr($decimal, 1), '0');
        } else {
            $decimal = ltrim($decimal, '0');
        }

        if ($decimal === '' || $decimal === '-') {
            $decimal = '0';
        }

        return new Money($decimal, $currency);
    }
}

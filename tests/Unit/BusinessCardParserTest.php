<?php

namespace Tests\Unit;

use App\Support\BusinessCardParser;
use PHPUnit\Framework\TestCase;

class BusinessCardParserTest extends TestCase
{
    public function test_extracts_contact_fields_from_malaysian_card_text(): void
    {
        $text = <<<'TEXT'
Aina Rahman
Geospatial Director
NADI Spatial Sdn Bhd
aina@nadispatial.my
+60 12-345 6789
www.nadispatial.my
12 Jalan Teknokrat, Cyberjaya, Selangor
TEXT;

        $parsed = BusinessCardParser::parse($text);

        $this->assertSame('Aina Rahman', $parsed['full_name']);
        $this->assertSame('Geospatial Director', $parsed['job_title']);
        $this->assertSame('NADI Spatial Sdn Bhd', $parsed['company_name']);
        $this->assertSame('aina@nadispatial.my', $parsed['email']);
        $this->assertSame('+60123456789', $parsed['phone']);
        $this->assertSame('https://www.nadispatial.my', $parsed['website']);
        $this->assertSame('12 Jalan Teknokrat, Cyberjaya, Selangor', $parsed['address']);
    }

    public function test_fill_blanks_keeps_values_the_phone_already_sent(): void
    {
        $filled = BusinessCardParser::fillBlanks([
            'full_name' => 'Booth Typed',
            'job_title' => null,
            'company_name' => null,
            'email' => null,
            'phone' => null,
            'website' => null,
            'address' => null,
        ], "Booth Typed\nLead Engineer\nhello@maps.test");

        $this->assertSame('Booth Typed', $filled['full_name']);
        $this->assertSame('Lead Engineer', $filled['job_title']);
        $this->assertSame('hello@maps.test', $filled['email']);
    }

    public function test_company_lines_are_not_taken_as_the_name(): void
    {
        $parsed = BusinessCardParser::parse("Geospatial Solutions\nAina Rahman\naina@nadi.my");

        $this->assertSame('Aina Rahman', $parsed['full_name']);
    }

    public function test_name_falls_back_to_a_personal_email_address(): void
    {
        $this->assertSame('Aina Rahman', BusinessCardParser::parse('aina.rahman@nadi.my 0123456789')['full_name']);
        $this->assertNull(BusinessCardParser::parse('sales@nadi.my 0123456789')['full_name']);
    }
}

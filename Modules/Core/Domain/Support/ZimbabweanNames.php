<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support;

/**
 * A small, hand-picked pool of common Zimbabwean (Shona and Ndebele) given
 * names and surnames, for demo/test data seeders. Faker's bundled locales
 * have no Zimbabwean provider, so `fake()->firstName()` produces generic
 * Western names — every `serp:seed:finance-*` command uses this instead so
 * demo students, guardians, and staff actually look like a Zimbabwean
 * school's real roll, not a random name generator's output.
 */
final class ZimbabweanNames
{
    /**
     * @var array<int, string>
     */
    private const array MALE_FIRST_NAMES = [
        'Tapiwa', 'Tendai', 'Farai', 'Tinashe', 'Munashe', 'Takudzwa', 'Kudakwashe',
        'Tatenda', 'Simbarashe', 'Panashe', 'Blessing', 'Tafadzwa', 'Tawanda',
        'Wellington', 'Emmanuel', 'Brighton', 'Ngonidzashe', 'Tonderai', 'Nyasha',
        'Innocent', 'Prosper', 'Praise', 'Anesu', 'Kelvin', 'Elton', 'Gift',
        'Sibusiso', 'Mthokozisi', 'Nkosana', 'Thabani', 'Nkosilathi', 'Sipho',
        'Bongani', 'Mandla', 'Nqobizitha', 'Thulani', 'Nkululeko', 'Prince',
    ];

    /**
     * @var array<int, string>
     */
    private const array FEMALE_FIRST_NAMES = [
        'Rutendo', 'Rumbidzai', 'Tadiwanashe', 'Tsitsi', 'Chiedza', 'Ropafadzo',
        'Vimbai', 'Nyasha', 'Rufaro', 'Kudzai', 'Panashe', 'Tariro', 'Fadzai',
        'Memory', 'Precious', 'Privilege', 'Patience', 'Charity', 'Constance',
        'Chipo', 'Sarudzai', 'Anesu', 'Nomsa', 'Sithembile', 'Nokuthula',
        'Nomthandazo', 'Thandiwe', 'Nomvula', 'Sibongile', 'Buhle', 'Andiswa',
        'Simangaliso', 'Lindiwe', 'Nozipho', 'Precious', 'Definite', 'Prudence',
    ];

    /**
     * @var array<int, string>
     */
    private const array SURNAMES = [
        'Moyo', 'Ncube', 'Sibanda', 'Dube', 'Ndlovu', 'Mahachi', 'Chikwanha',
        'Mutasa', 'Chiweshe', 'Gumbo', 'Chirwa', 'Mudzingwa', 'Nyathi', 'Muleya',
        'Chidziva', 'Marufu', 'Chinamasa', 'Mangwiro', 'Chikafu', 'Zvobgo',
        'Mapfumo', 'Chirimuuta', 'Gwatidzo', 'Mavhunga', 'Chidzambwa', 'Mazorodze',
        'Sithole', 'Khumalo', 'Nkomo', 'Nyoni', 'Mpofu', 'Zulu', 'Tshuma',
        'Mlambo', 'Gumede', 'Mabhena', 'Chitima', 'Muzenda', 'Chombo', 'Bvute',
        'Chikowore', 'Manyame', 'Chiromo', 'Guveya', 'Chidyausiku', 'Mushonga',
    ];

    /**
     * @var array<int, string>
     */
    private const array MOBILE_PREFIXES = ['71', '73', '77', '78'];

    public static function firstName(string $gender): string
    {
        $pool = $gender === 'female' ? self::FEMALE_FIRST_NAMES : self::MALE_FIRST_NAMES;

        return $pool[array_rand($pool)];
    }

    public static function surname(): string
    {
        return self::SURNAMES[array_rand(self::SURNAMES)];
    }

    public static function fullName(string $gender): string
    {
        return self::firstName($gender).' '.self::surname();
    }

    /**
     * `+263 7X XXX XXXX` — the shape of every Zimbabwean mobile network
     * (Econet/NetOne/Telecel) number.
     */
    public static function mobilePhone(): string
    {
        $prefix = self::MOBILE_PREFIXES[array_rand(self::MOBILE_PREFIXES)];

        return '+263'.$prefix.random_int(1000000, 9999999);
    }

    public static function email(string $firstName, string $lastName, string $domain = 'example.zw'): string
    {
        $slug = mb_strtolower($firstName.'.'.$lastName);
        $slug = preg_replace('/[^a-z0-9.]/', '', $slug) ?? $slug;

        return $slug.random_int(1, 999).'@'.$domain;
    }
}

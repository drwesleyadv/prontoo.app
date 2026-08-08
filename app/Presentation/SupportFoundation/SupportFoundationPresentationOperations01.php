<?php
declare(strict_types=1);

namespace Prontoo\Presentation\SupportFoundation;

use \Closure;
use \DateInterval;
use \DateTime;
use \DateTimeImmutable;
use \DateTimeInterface;
use \DateTimeZone;
use \Exception;
use \GdImage;
use \InvalidArgumentException;
use \JsonException;
use \LogicException;
use \PDO;
use \PDOException;
use \ProntooHttpError;
use \RuntimeException;
use \Throwable;

final class SupportFoundationPresentationOperations01
{
    private function __construct()
    {
    }

    public static function request_host_raw(): string
    
    {
    
        $host = (string) ($_SERVER["HTTP_HOST"] ?? "localhost");
        $host = preg_replace("/[^a-zA-Z0-9\.\-:\[\]]/", "", $host) ?: "localhost";
        return $host;
    
    }

    public static function base_path(): string
    
    {
    
        $script = (string) ($_SERVER["SCRIPT_NAME"] ?? "/");
        $dir = rtrim(str_replace(["install.php", "index.php"], "", $script), "/");
        return $dir ?: "";
    
    }

    public static function route(): string
    
    {
    
        return preg_replace("/[^a-z0-9_\-]/i", "", $_GET["r"] ?? "login") ?:
            "login";
    
    }

    public static function person_common_profile_fields_html(
        int $cid,
        array $p = [],
        string $prefix = "",
        bool $includeEmail = true,
        bool $includePhone = true,
    ): string 
    {
    
        $get = function (string $k, string $def = "") use ($p) {
    
            return (string) ($p[$k] ?? $def);
        };
        $uf = $get("address_state");
        $city = $get("address_city");
        $cityIbge = $get("address_city_ibge");
        $cityOptions =
            $city !== ""
                ? '<option value="' .
                    e($city) .
                    '" data-ibge="' .
                    e($cityIbge) .
                    '" selected>' .
                    e($city) .
                    "</option>"
                : '<option value="">Escolha primeiro o estado</option>';
        $states =
            ["" => "Escolha o estado"] +
            (function_exists("br_states") ? br_states() : []);
        $contact = "";
        $contactFields = "";
        if ($includePhone) {
            $contactFields .= form_row(
                "Telefone",
                input(
                    $prefix . "phone",
                    "text",
                    $get("phone"),
                    'autocomplete="tel" inputmode="tel" placeholder="(00) 00000-0000"',
                ),
            );
        }
        if ($includeEmail) {
            $contactFields .= form_row(
                "E-mail",
                input(
                    $prefix . "email",
                    "email",
                    $get("email"),
                    'autocomplete="email"',
                ),
            );
        }
        if ($contactFields !== "") {
            $contact = '<div class="two">' . $contactFields . "</div>";
        }
        return $contact .
            '<section class="patient-address-block person-common-address-block" data-patient-address-block>' .
            '<div class="two">' .
            form_row(
                "CEP",
                input(
                    $prefix . "address_zip",
                    "text",
                    $get("address_zip"),
                    'inputmode="numeric" maxlength="9" autocomplete="postal-code" data-patient-address-zip',
                ),
            ) .
            form_row(
                "Logradouro",
                input(
                    $prefix . "address",
                    "text",
                    $get("address"),
                    'maxlength="255" autocomplete="address-line1" data-patient-address-street',
                ),
            ) .
            '</div><div class="two">' .
            form_row(
                "Número",
                input(
                    $prefix . "address_number",
                    "text",
                    $get("address_number"),
                    'maxlength="20" autocomplete="address-line2" data-patient-address-number',
                ),
            ) .
            form_row(
                "Bairro",
                input(
                    $prefix . "address_neighborhood",
                    "text",
                    $get("address_neighborhood"),
                    'maxlength="120" data-patient-address-neighborhood',
                ),
            ) .
            '</div><div class="two">' .
            form_row(
                "Complemento",
                input(
                    $prefix . "address_complement",
                    "text",
                    $get("address_complement"),
                    'maxlength="120" autocomplete="address-line3" data-patient-address-complement',
                ),
            ) .
            select_label(
                "Estado",
                $prefix . "address_state",
                $states,
                $uf,
                "data-br-state data-patient-address-state",
            ) .
            "</div>" .
            form_row(
                "Cidade",
                '<select name="' .
                    e($prefix . "address_city") .
                    '" data-br-city data-selected-city="' .
                    e($city) .
                    '" data-patient-address-city>' .
                    $cityOptions .
                    '</select><input type="hidden" name="' .
                    e($prefix . "address_city_ibge") .
                    '" value="' .
                    e($cityIbge) .
                    '" data-br-city-ibge data-patient-address-ibge>',
            ) .
            "</section>";
    
    }
}

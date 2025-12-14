<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Engine;

use DOMDocument;
use InvalidArgumentException;
use LibXMLError;

class XmlValidator
{
 /**
  * Path to the XSD schema file.
  *
  * @var string|null
  */
    protected static ?string $xsdPath = null;

    /**
     * Set the path to the XSD schema file.
     *
     * @param string $path
     *
     * @return void
     */
    public static function setXsdPath(string $path): void
    {
        static::$xsdPath = $path;
    }

    /**
     * Get the path to the XSD schema file.
     *
     * @return string
     */
    public static function getXsdPath(): string
    {
        if (static::$xsdPath !== null) {
            return static::$xsdPath;
        }

        // Default to the bundled XSD in the package
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'dto.xsd';
    }

    /**
     * @param string $file
     *
     * @throws \InvalidArgumentException
     *
     * @return void
     */
    public static function validate(string $file): void
    {
        // Enable user error handling
        libxml_use_internal_errors(true);

        $xml = new DOMDocument();
        $xml->load($file);

        $xsd = static::getXsdPath();
        if (!$xml->schemaValidate($xsd)) {
            $errors = static::getErrors();

            throw new InvalidArgumentException(implode("\n", $errors));
        }
    }

    /**
     * @param \LibXMLError $error
     *
     * @return string|null
     */
    public static function formatError(LibXMLError $error): ?string
    {
        $header = null;
        switch ($error->level) {
            case LIBXML_ERR_WARNING:
                // We dont care for warnings right now, only hard fails.
                //$header = "Warning $error->code";
                break;
            case LIBXML_ERR_ERROR:
                $header = "Error `$error->code`";

                break;
            case LIBXML_ERR_FATAL:
                $header = "Fatal Error `$error->code`";

                break;
        }

        if ($header === null) {
            return $header;
        }

        $errorMessage = $header . ' ' . trim($error->message);
        if ($error->file) {
            $errorMessage .= " in `$error->file`";
        }
        $errorMessage .= " on line `$error->line`";

        return $errorMessage;
    }

    /**
     * @return array<string>
     */
    public static function getErrors(): array
    {
        $errors = libxml_get_errors();

        $result = [];
        foreach ($errors as $error) {
            $return = static::formatError($error);
            if (!$return) {
                continue;
            }

            $result[] = $return;
        }
        libxml_clear_errors();

        return $result;
    }
}

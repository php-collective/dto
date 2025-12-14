<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Filesystem;

use DirectoryIterator;
use Exception;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Folder structure browser, lists folders and files.
 * Provides an Object interface for Common directory related tasks.
 */
class Folder
{
    /**
     * Default scheme for Folder::copy
     * Recursively merges subfolders with the same name
     *
     * @var string
     */
    public const MERGE = 'merge';

    /**
     * Overwrite scheme for Folder::copy
     * subfolders with the same name will be replaced
     *
     * @var string
     */
    public const OVERWRITE = 'overwrite';

    /**
     * Skip scheme for Folder::copy
     * if a subfolder with the same name exists it will be skipped
     *
     * @var string
     */
    public const SKIP = 'skip';

    /**
     * Sort mode by name
     *
     * @var string
     */
    public const SORT_NAME = 'name';

    /**
     * Sort mode by time
     *
     * @var string
     */
    public const SORT_TIME = 'time';

    /**
     * Path to Folder.
     */
    public ?string $path = null;

    /**
     * Sortedness. Whether list results
     * should be sorted by name.
     */
    public bool $sort = false;

    /**
     * Mode to be used on create. Does nothing on windows platforms.
     */
    public int $mode = 0755;

    /**
     * Functions array to be called depending on the sort type chosen.
     *
     * @var array<string>
     */
    protected array $_fsorts = [
        self::SORT_NAME => 'getPathname',
        self::SORT_TIME => 'getCTime',
    ];

    /**
     * Holds messages from last method.
     *
     * @var array<string>
     */
    protected array $_messages = [];

    /**
     * Holds errors from last method.
     *
     * @var array<string>
     */
    protected array $_errors = [];

    /**
     * Constructor.
     *
     * @param string|null $path Path to folder
     * @param bool $create Create folder if not found
     * @param int|null $mode Mode (CHMOD) to apply to created folder, false to ignore
     */
    public function __construct(?string $path = null, bool $create = false, ?int $mode = null)
    {
        if (!$path) {
            $path = sys_get_temp_dir();
        }
        if ($mode) {
            $this->mode = $mode;
        }

        if (!file_exists($path) && $create === true) {
            $this->create($path, $this->mode);
        }
        if (!static::isAbsolute($path)) {
            $path = realpath($path);
        }
        if ($path) {
            $this->cd($path);
        }
    }

    /**
     * Return current path.
     *
     * @return string|null Current path
     */
    public function pwd(): ?string
    {
        return $this->path;
    }

    /**
     * Change directory to $path.
     *
     * @param string $path Path to the directory to change to
     *
     * @return string|false The new path. Returns false on failure
     */
    public function cd(string $path)
    {
        $path = $this->realpath($path);
        if ($path !== false && is_dir($path)) {
            return $this->path = $path;
        }

        return false;
    }

    /**
     * Returns an array of the contents of the current directory.
     * The returned array holds two arrays: One of directories and one of files.
     *
     * @param string|bool $sort Whether you want the results sorted, set this and the sort property
     *   to `false` to get unsorted results.
     * @param array<string>|bool $exceptions Either an array or boolean true will not grab dot files
     * @param bool $fullPath True returns the full path
     *
     * @return array<array<string>> Contents of current directory as an array, an empty array on failure
     */
    public function read($sort = self::SORT_NAME, $exceptions = false, bool $fullPath = false): array
    {
        $dirs = $files = [];

        if (!$this->pwd()) {
            return [$dirs, $files];
        }
        if (is_array($exceptions)) {
            $exceptions = array_flip($exceptions);
        }
        $skipHidden = isset($exceptions['.']) || $exceptions === true;

        try {
            $iterator = new DirectoryIterator((string)$this->path);
        } catch (Exception $e) {
            return [$dirs, $files];
        }

        if (!is_bool($sort) && isset($this->_fsorts[$sort])) {
            $methodName = $this->_fsorts[$sort];
        } else {
            $methodName = $this->_fsorts[static::SORT_NAME];
        }

        foreach ($iterator as $item) {
            if ($item->isDot()) {
                continue;
            }
            $name = $item->getFilename();
            if ($skipHidden && $name[0] === '.' || isset($exceptions[$name])) {
                continue;
            }
            if ($fullPath) {
                $name = $item->getPathname();
            }

            if ($item->isDir()) {
                $dirs[$item->{$methodName}()][] = $name;
            } else {
                $files[$item->{$methodName}()][] = $name;
            }
        }

        if ($sort || $this->sort) {
            ksort($dirs);
            ksort($files);
        }

        if ($dirs) {
            $dirs = array_merge(...array_values($dirs));
        }

        if ($files) {
            $files = array_merge(...array_values($files));
        }

        return [$dirs, $files];
    }

    /**
     * Returns an array of all matching files in current directory.
     *
     * @param string $regexpPattern Preg_match pattern (Defaults to: .*)
     * @param string|bool $sort Whether results should be sorted.
     *
     * @return array<string> Files that match given pattern
     */
    public function find(string $regexpPattern = '.*', $sort = false): array
    {
        [, $files] = $this->read($sort);

        return array_values(preg_grep('/^' . $regexpPattern . '$/i', $files) ?: []);
    }

    /**
     * Returns an array of all matching files in and below current directory.
     *
     * @param string $pattern Preg_match pattern (Defaults to: .*)
     * @param bool $sort Whether results should be sorted.
     *
     * @return array<string> Files matching $pattern
     */
    public function findRecursive(string $pattern = '.*', bool $sort = false): array
    {
        if (!$this->pwd()) {
            return [];
        }
        $startsOn = (string)$this->path;
        $out = $this->_findRecursive($pattern, $sort);
        $this->cd($startsOn);

        return $out;
    }

    /**
     * Private helper function for findRecursive.
     *
     * @param string $pattern Pattern to match against
     * @param bool $sort Whether results should be sorted.
     *
     * @return array<string> Files matching pattern
     */
    protected function _findRecursive(string $pattern, bool $sort = false): array
    {
        [$dirs, $files] = $this->read($sort);
        $found = [];

        foreach ($files as $file) {
            if (preg_match('/^' . $pattern . '$/i', $file)) {
                $found[] = static::addPathElement((string)$this->path, $file);
            }
        }
        $start = (string)$this->path;

        foreach ($dirs as $dir) {
            $this->cd(static::addPathElement($start, $dir));
            $found = array_merge($found, $this->findRecursive($pattern, $sort));
        }

        return $found;
    }

    /**
     * Returns true if given $path is a Windows path.
     *
     * @param string $path Path to check
     *
     * @return bool true if windows path, false otherwise
     */
    public static function isWindowsPath(string $path): bool
    {
        return preg_match('/^[A-Z]:\\\\/i', $path) || substr($path, 0, 2) === '\\\\';
    }

    /**
     * Returns true if given $path is an absolute path.
     *
     * @param string $path Path to check
     *
     * @return bool true if path is absolute.
     */
    public static function isAbsolute(string $path): bool
    {
        if (!$path) {
            return false;
        }

        return $path[0] === '/' ||
            preg_match('/^[A-Z]:\\\\/i', $path) ||
            substr($path, 0, 2) === '\\\\' ||
            static::isRegisteredStreamWrapper($path);
    }

    /**
     * Returns true if given $path is a registered stream wrapper.
     *
     * @param string $path Path to check
     *
     * @return bool True if path is registered stream wrapper.
     */
    public static function isRegisteredStreamWrapper(string $path): bool
    {
        return preg_match('/^[^:\/]+?(?=:\/\/)/', $path, $matches) &&
            in_array($matches[0], stream_get_wrappers(), true);
    }

    /**
     * Returns a correct set of slashes for given $path. (\\ for Windows paths and / for other paths.)
     *
     * @param string $path Path to transform
     *
     * @return string Path with the correct set of slashes ("\\" or "/")
     */
    public static function normalizeFullPath(string $path): string
    {
        $to = static::correctSlashFor($path);
        $from = ($to === '/' ? '\\' : '/');

        return str_replace($from, $to, $path);
    }

    /**
     * Returns a correct set of slashes for given $path. (\\ for Windows paths and / for other paths.)
     *
     * @param string $path Path to check
     *
     * @return string Set of slashes ("\\" or "/")
     */
    public static function correctSlashFor(string $path): string
    {
        return static::isWindowsPath($path) ? '\\' : '/';
    }

    /**
     * Returns $path with added terminating slash (corrected for Windows or other OS).
     *
     * @param string $path Path to check
     *
     * @return string Path with ending slash
     */
    public static function slashTerm(string $path): string
    {
        if (static::isSlashTerm($path)) {
            return $path;
        }

        return $path . static::correctSlashFor($path);
    }

    /**
     * Returns $path with $element added, with correct slash in-between.
     *
     * @param string $path Path
     * @param array<string>|string $element Element to add at end of path
     *
     * @return string Combined path
     */
    public static function addPathElement(string $path, $element): string
    {
        $element = (array)$element;
        array_unshift($element, rtrim($path, DIRECTORY_SEPARATOR));

        return implode(DIRECTORY_SEPARATOR, $element);
    }

    /**
     * Returns true if the Folder is in the given path.
     *
     * @param string $path The absolute path to check that the current `pwd()` resides within.
     * @param bool $reverse Reverse the search, check if the given `$path` resides within the current `pwd()`.
     *
     * @throws \InvalidArgumentException When the given `$path` argument is not an absolute path.
     *
     * @return bool
     */
    public function inPath(string $path, bool $reverse = false): bool
    {
        if (!static::isAbsolute($path)) {
            throw new InvalidArgumentException('The $path argument is expected to be an absolute path.');
        }

        $dir = static::slashTerm($path);
        $current = static::slashTerm((string)$this->pwd());

        if (!$reverse) {
            $return = preg_match('/^' . preg_quote($dir, '/') . '(.*)/', $current);
        } else {
            $return = preg_match('/^' . preg_quote($current, '/') . '(.*)/', $dir);
        }

        return (bool)$return;
    }

    /**
     * Create a directory structure recursively.
     *
     * Can be used to create deep path structures like `/foo/bar/baz/shoe/horn`
     *
     * @param string $pathname The directory structure to create. Either an absolute or relative
     *   path. If the path is relative and exists in the process' cwd it will not be created.
     *   Otherwise, relative paths will be prefixed with the current pwd().
     * @param int|null $mode octal value 0755
     *
     * @return bool Returns TRUE on success, FALSE on failure
     */
    public function create(string $pathname, ?int $mode = null): bool
    {
        if (is_dir($pathname) || !$pathname) {
            return true;
        }

        if (!static::isAbsolute($pathname)) {
            $pathname = static::addPathElement((string)$this->pwd(), $pathname);
        }

        if (!$mode) {
            $mode = $this->mode;
        }

        if (is_file($pathname)) {
            $this->_errors[] = sprintf('%s is a file', $pathname);

            return false;
        }
        $pathname = rtrim($pathname, DIRECTORY_SEPARATOR);
        $nextPathname = substr($pathname, 0, (int)strrpos($pathname, DIRECTORY_SEPARATOR));

        if ($this->create($nextPathname, $mode)) {
            if (!file_exists($pathname)) {
                $old = umask(0);
                if (mkdir($pathname, $mode, true)) {
                    $this->_messages[] = sprintf('%s created', $pathname);
                    umask($old);

                    return true;
                }
                $this->_errors[] = sprintf('%s NOT created', $pathname);
                umask($old);

                return false;
            }
        }

        return false;
    }

    /**
     * Recursively Remove directories if the system allows.
     *
     * @param string|null $path Path of directory to delete
     *
     * @return bool Success
     */
    public function delete(?string $path = null): bool
    {
        if (!$path) {
            $path = $this->pwd();
        }
        if (!$path) {
            return false;
        }
        $path = static::slashTerm($path);
        if (is_dir($path)) {
            $directory = $iterator = null;
            try {
                $directory = new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::CURRENT_AS_SELF);
                $iterator = new RecursiveIteratorIterator($directory, RecursiveIteratorIterator::CHILD_FIRST);
            } catch (Exception $e) {
                unset($directory, $iterator);

                return false;
            }

            foreach ($iterator as $item) {
                $filePath = $item->getPathname();
                if ($item->isFile() || $item->isLink()) {
                    // phpcs:disable
                    if (@unlink($filePath)) {
                        // phpcs:enable
                        $this->_messages[] = sprintf('%s removed', $filePath);
                    } else {
                        $this->_errors[] = sprintf('%s NOT removed', $filePath);
                    }
                } elseif ($item->isDir() && !$item->isDot()) {
                    // phpcs:disable
                    if (@rmdir($filePath)) {
                        // phpcs:enable
                        $this->_messages[] = sprintf('%s removed', $filePath);
                    } else {
                        $this->_errors[] = sprintf('%s NOT removed', $filePath);

                        unset($directory, $iterator, $item);

                        return false;
                    }
                }

                // inner iterators need to be unset too in order for locks on parents to be released
                unset($item);
            }

            // unsetting iterators helps releasing possible locks in certain environments,
            // which could otherwise make `rmdir()` fail
            unset($directory, $iterator);

            $path = rtrim($path, DIRECTORY_SEPARATOR);
            // phpcs:disable
            if (@rmdir($path)) {
                // phpcs:enable
                $this->_messages[] = sprintf('%s removed', $path);
            } else {
                $this->_errors[] = sprintf('%s NOT removed', $path);

                return false;
            }
        }

        return true;
    }

    /**
     * Get the real path (taking ".." and such into account)
     *
     * @param string $path Path to resolve
     *
     * @return string|false The resolved path
     */
    public function realpath(string $path)
    {
        if (strpos($path, '..') === false) {
            if (!static::isAbsolute($path)) {
                $path = static::addPathElement((string)$this->path, $path);
            }

            return $path;
        }
        $path = str_replace('/', DIRECTORY_SEPARATOR, trim($path));
        $parts = explode(DIRECTORY_SEPARATOR, $path);
        $newparts = [];
        $newpath = '';
        if ($path[0] === DIRECTORY_SEPARATOR) {
            $newpath = DIRECTORY_SEPARATOR;
        }

        while (($part = array_shift($parts)) !== null) {
            if ($part === '.' || $part === '') {
                continue;
            }
            if ($part === '..') {
                if ($newparts) {
                    array_pop($newparts);

                    continue;
                }

                return false;
            }
            $newparts[] = $part;
        }
        $newpath .= implode(DIRECTORY_SEPARATOR, $newparts);

        return static::slashTerm($newpath);
    }

    /**
     * Returns true if given $path ends in a slash (i.e. is slash-terminated).
     *
     * @param string $path Path to check
     *
     * @return bool true if path ends with slash, false otherwise
     */
    public static function isSlashTerm(string $path): bool
    {
        $lastChar = $path[strlen($path) - 1];

        return $lastChar === '/' || $lastChar === '\\';
    }

    /**
     * get messages from latest method
     *
     * @param bool $reset Reset message stack after reading
     *
     * @return array<string>
     */
    public function messages(bool $reset = true): array
    {
        $messages = $this->_messages;
        if ($reset) {
            $this->_messages = [];
        }

        return $messages;
    }

    /**
     * get error from latest method
     *
     * @param bool $reset Reset error stack after reading
     *
     * @return array<string>
     */
    public function errors(bool $reset = true): array
    {
        $errors = $this->_errors;
        if ($reset) {
            $this->_errors = [];
        }

        return $errors;
    }
}

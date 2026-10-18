<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_classprogress;

/**
 * Every method of the plugin's classes that overrides or implements a parent or interface method says so with
 * #[\Override], so PHP 8.3 and later check that the method it overrides still exists.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class override_test extends \advanced_testcase {
    /**
     * Lists the methods that override a parent or interface method but lack #[\Override].
     */
    public function test_every_overriding_method_has_the_override_attribute(): void {
        $root = \core_component::get_component_directory('local_classprogress') . '/classes/';
        $missing = [];
        $checked = 0;
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            $class = 'local_classprogress\\' . str_replace('/', '\\', substr($file->getPathname(), strlen($root), -4));
            $reflection = new \ReflectionClass($class);
            foreach ($reflection->getMethods() as $method) {
                if ($method->getDeclaringClass()->getName() !== $class || $method->isConstructor()) {
                    continue;
                }
                if (!self::overrides($reflection, $method->getName())) {
                    continue;
                }
                $checked++;
                if (!$method->getAttributes('Override')) {
                    $missing[] = $class . '::' . $method->getName();
                }
            }
        }

        $this->assertGreaterThan(10, $checked);
        $this->assertSame([], $missing);
    }

    /**
     * Whether a parent class or an interface of a class declares a method that the class can override.
     *
     * @param \ReflectionClass $class The class.
     * @param string $name Method name.
     * @return bool True when the method overrides or implements another.
     */
    private static function overrides(\ReflectionClass $class, string $name): bool {
        $parent = $class->getParentClass();
        if ($parent && $parent->hasMethod($name) && !$parent->getMethod($name)->isPrivate()) {
            return true;
        }
        foreach ($class->getInterfaces() as $interface) {
            if ($interface->hasMethod($name)) {
                return true;
            }
        }
        return false;
    }
}

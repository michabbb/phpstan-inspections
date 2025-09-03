<?php

declare(strict_types=1);

// Test cases for MagicMethodsValidityRule
// This file contains various magic method implementations that should trigger errors

class TestMagicMethodsValidity {

    // __construct - should NOT trigger (valid)
    public function __construct() {}

    // __construct with return type - should trigger error
    public function __constructWithReturn(): void {}

    // __construct static - should trigger error
    public static function __constructStatic() {}

    // __destruct - should NOT trigger (valid)
    public function __destruct() {}

    // __destruct with arguments - should trigger error
    public function __destructWithArgs($arg) {}

    // __destruct static - should trigger error
    public static function __destructStatic() {}

    // __destruct with return type - should trigger error
    public function __destructWithReturn(): void {}

    // __clone - should NOT trigger (valid)
    public function __clone() {}

    // __clone with arguments - should trigger error
    public function __cloneWithArgs($arg) {}

    // __clone static - should trigger error
    public static function __cloneStatic() {}

    // __clone with return type - should trigger error
    public function __cloneWithReturn(): object {}

    // __get - should NOT trigger (valid)
    public function __get($name) {
        return $this->$name ?? null;
    }

    // __get with wrong number of args - should trigger error
    public function __getNoArgs() {}

    // __get with too many args - should trigger error
    public function __getTooManyArgs($name, $extra) {}

    // __get static - should trigger error
    public static function __getStatic($name) {}

    // __get private - should trigger error
    private function __getPrivate($name) {}

    // __get with by-ref param - should trigger error
    public function __getByRef(&$name) {}

    // __set - should NOT trigger (valid)
    public function __set($name, $value) {
        $this->$name = $value;
    }

    // __set with wrong number of args - should trigger error
    public function __setOneArg($name) {}

    // __set with too many args - should trigger error
    public function __setTooManyArgs($name, $value, $extra) {}

    // __set static - should trigger error
    public static function __setStatic($name, $value) {}

    // __set private - should trigger error
    private function __setPrivate($name, $value) {}

    // __set with by-ref param - should trigger error
    public function __setByRef($name, &$value) {}

    // __call - should NOT trigger (valid)
    public function __call($name, $arguments) {
        return call_user_func_array([$this, $name], $arguments);
    }

    // __call with wrong number of args - should trigger error
    public function __callOneArg($name) {}

    // __call with too many args - should trigger error
    public function __callTooManyArgs($name, $arguments, $extra) {}

    // __call static - should trigger error
    public static function __callStaticWrong($name, $arguments) {}

    // __call private - should trigger error
    private function __callPrivate($name, $arguments) {}

    // __call with by-ref param - should trigger error
    public function __callByRef($name, &$arguments) {}

    // __callStatic - should NOT trigger (valid)
    public static function __callStatic($name, $arguments) {
        return call_user_func_array(['static', $name], $arguments);
    }

    // __callStatic with wrong number of args - should trigger error
    public static function __callStaticOneArg($name) {}

    // __callStatic with too many args - should trigger error
    public static function __callStaticTooManyArgs($name, $arguments, $extra) {}

    // __callStatic non-static - should trigger error
    public function __callStaticNonStatic($name, $arguments) {}

    // __callStatic private - should trigger error
    private static function __callStaticPrivate($name, $arguments) {}

    // __callStatic with by-ref param - should trigger error
    public static function __callStaticByRef($name, &$arguments) {}

    // __toString - should NOT trigger (valid)
    public function __toString(): string {
        return 'TestMagicMethodsValidity';
    }

    // __toString with arguments - should trigger error
    public function __toStringWithArgs($arg): string {}

    // __toString static - should trigger error
    public static function __toStringStatic(): string {}

    // __toString private - should trigger error
    private function __toStringPrivate(): string {}

    // __toString wrong return type - should trigger error
    public function __toStringWrongReturn(): int {}

    // __toString no return type - should NOT trigger (allowed)

    // __debugInfo - should NOT trigger (valid)
    public function __debugInfo(): array {
        return ['class' => self::class];
    }

    // __debugInfo with arguments - should trigger error
    public function __debugInfoWithArgs($arg): array {}

    // __debugInfo static - should trigger error
    public static function __debugInfoStatic(): array {}

    // __debugInfo private - should trigger error
    private function __debugInfoPrivate(): array {}

    // __debugInfo wrong return type - should trigger error
    public function __debugInfoWrongReturn(): string {}

    // __debugInfo nullable return type - should NOT trigger (valid)
    public function __debugInfoNullable(): ?array {}

    // __set_state - should NOT trigger (valid)
    public static function __set_state(array $properties): self {
        $obj = new self();
        foreach ($properties as $key => $value) {
            $obj->$key = $value;
        }
        return $obj;
    }

    // __set_state with wrong number of args - should trigger error
    public static function __set_stateNoArgs(): self {}

    // __set_state with too many args - should trigger error
    public static function __set_stateTooManyArgs(array $properties, $extra): self {}

    // __set_state non-static - should trigger error
    public function __set_stateNonStatic(array $properties): self {}

    // __set_state private - should trigger error
    private static function __set_statePrivate(array $properties): self {}

    // __set_state wrong return type - should trigger error
    public static function __set_stateWrongReturn(array $properties): string {}

    // __invoke - should NOT trigger (valid)
    public function __invoke() {
        return 'invoked';
    }

    // __invoke static - should trigger error
    public static function __invokeStatic() {}

    // __invoke private - should trigger error
    private function __invokePrivate() {}

    // __wakeup - should NOT trigger (valid)
    public function __wakeup() {}

    // __wakeup with arguments - should trigger error
    public function __wakeupWithArgs($arg) {}

    // __wakeup static - should trigger error
    public static function __wakeupStatic() {}

    // __wakeup with return type - should trigger error
    public function __wakeupWithReturn(): void {}

    // __unserialize - should NOT trigger (valid)
    public function __unserialize(array $data): void {}

    // __unserialize with wrong number of args - should trigger error
    public function __unserializeNoArgs(): void {}

    // __unserialize with too many args - should trigger error
    public function __unserializeTooManyArgs(array $data, $extra): void {}

    // __unserialize static - should trigger error
    public static function __unserializeStatic(array $data): void {}

    // __unserialize private - should trigger error
    private function __unserializePrivate(array $data): void {}

    // __unserialize with return type - should trigger error
    public function __unserializeWithReturn(array $data): string {}

    // __sleep - should NOT trigger (valid)
    public function __sleep(): array {
        return ['property'];
    }

    // __sleep with arguments - should trigger error
    public function __sleepWithArgs($arg): array {}

    // __sleep static - should trigger error
    public static function __sleepStatic(): array {}

    // __sleep private - should trigger error
    private function __sleepPrivate(): array {}

    // __sleep wrong return type - should trigger error
    public function __sleepWrongReturn(): string {}

    // __serialize - should NOT trigger (valid)
    public function __serialize(): array {
        return ['property' => $this->property ?? null];
    }

    // __serialize with arguments - should trigger error
    public function __serializeWithArgs($arg): array {}

    // __serialize static - should trigger error
    public static function __serializeStatic(): array {}

    // __serialize private - should trigger error
    private function __serializePrivate(): array {}

    // __serialize wrong return type - should trigger error
    public function __serializeWrongReturn(): string {}

    // __autoload - should trigger deprecation warning
    public function __autoload($class) {}

    // Non-magic method starting with __ - should trigger error
    public function __myCustomMethod() {}

    // Another non-magic method starting with __ - should trigger error
    public function __anotherMethod($arg) {}

    // Known non-magic method __inject - should NOT trigger
    public function __inject($dependency) {}

    // Known non-magic method __prepare - should NOT trigger
    public function __prepare() {}

    // Known non-magic method __toArray - should NOT trigger
    public function __toArray() {}

    // Known non-magic method __soapCall - should NOT trigger
    public function __soapCall($method, $args) {}
}
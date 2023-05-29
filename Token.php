<?php

namespace Markpruett571\TrunkTokenizer;

class Token
{
    /**
     * A string wrapper with a `spacing` attribute that
     * describes the prefix after which this token was split
     * and an `offset` attribute to describe its position in
     * the text being tokenized.
     *
     * Typically, the prefix will be a single whitespace character, but it
     * can be really anything found between the current and the last token
     * (or anything before the first token in the text).
     *
     * The offset represents the Token's position in the original text.
     *
     * Two Tokens are equal if they share the same value,
     * no matter their spacing and offsets.
     */

    public $spacing;
    public $value;
    public $offset;

    public function __construct($space_prefix, $value, $offset) {
        $this->spacing = $space_prefix;
        $this->value = $value;
        $this->offset = $offset;
    }

    public function __toString() {
        return $this->spacing . $this->value;
    }

    public function equals($other) {
        if ($other == null || !($other instanceof self)) {
            return false;
        }

        return $other->getValue() == $this->value;
    }

    public function getValue() {
        return $this->value;
    }

    public function getSpacing() {
        return $this->spacing;
    }

    public function getOffset() {
        return $this->offset;
    }

    public function update($val) {
        $this->offset += $val;
    }
}
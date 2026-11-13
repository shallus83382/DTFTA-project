import test from 'node:test';
import assert from 'node:assert/strict';
import { guessColorSwatch, parseEntryName } from '../../resources/js/bulk-mockup-import.js';

test('guesses editable swatches from common and standard color names', () => {
    assert.equal(guessColorSwatch('Red'), '#ff0000');
    assert.equal(guessColorSwatch('forest green'), '#228b22');
    assert.equal(guessColorSwatch('hot-pink'), '#ff69b4');
    assert.equal(guessColorSwatch('custom fabric shade'), null);
});

test('parses color folders and placement filenames', () => {
    const cases = [
        ['red/back.png', 'red', 'back'],
        ['red/front.png', 'red', 'front'],
        ['red/left.png', 'red', 'left_sleeve'],
        ['red/right.png', 'red', 'right_sleeve'],
        ['mockups/red/Left Sleeve.png', 'red', 'left_sleeve'],
        ['Front-Red.png', 'red', 'front'],
        ['red_front.png', 'red', 'front'],
    ];

    cases.forEach(([path, color, placement]) => {
        const result = parseEntryName(path);
        assert.equal(result.color.toLowerCase(), color, path);
        assert.equal(result.placement, placement, path);
        assert.equal(result.ambiguousColor, false, path);
        assert.equal(result.ambiguousPlacement, false, path);
    });
});

test('flags conflicting folder and filename colors and ambiguous placements', () => {
    const conflictingColor = parseEntryName('red/blue_front.png');
    assert.equal(conflictingColor.color, 'blue');
    assert.equal(conflictingColor.ambiguousColor, true);

    const ambiguousPlacement = parseEntryName('red/front-back.png');
    assert.equal(ambiguousPlacement.color, 'red');
    assert.equal(ambiguousPlacement.ambiguousPlacement, true);
});

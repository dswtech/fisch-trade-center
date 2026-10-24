import { describe, expect, it } from 'vitest';
import { hasInvertedValueRange } from './search';

describe('search filters', () => {
    it('rejects a maximum below the minimum', () => {
        expect(hasInvertedValueRange('2400', '2399')).toBe(true);
    });

    it('allows equal, open, and valid ranges', () => {
        expect(hasInvertedValueRange('2400', '2400')).toBe(false);
        expect(hasInvertedValueRange('2400', '')).toBe(false);
        expect(hasInvertedValueRange('', '2400')).toBe(false);
        expect(hasInvertedValueRange('1000', '2400')).toBe(false);
    });
});


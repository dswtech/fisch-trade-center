export function hasInvertedValueRange(minimum: string, maximum: string): boolean {
    if (minimum === '' || maximum === '') return false;

    const min = Number(minimum);
    const max = Number(maximum);

    return Number.isFinite(min) && Number.isFinite(max) && max < min;
}


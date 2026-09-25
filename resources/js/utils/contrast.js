const HEX_PATTERN = /^#([0-9a-f]{3}|[0-9a-f]{6})$/i;
const CHANNEL_MAX = 255;
const SRGB_KNEE = 0.04045;
const SRGB_LINEAR_SLOPE = 12.92;
const SRGB_OFFSET = 0.055;
const SRGB_SCALE = 1.055;
const SRGB_GAMMA = 2.4;
const LUMINANCE_WEIGHTS = [0.2126, 0.7152, 0.0722];
const FLARE = 0.05;

export class InvalidHexColorError extends Error {
    constructor(value) {
        super(`Invalid hex colour: ${JSON.stringify(value)}`);
        this.name = 'InvalidHexColorError';
    }
}

function parseHex(hex) {
    if (typeof hex !== 'string' || !HEX_PATTERN.test(hex)) {
        throw new InvalidHexColorError(hex);
    }

    const digits = hex.slice(1);
    const full = digits.length === 3 ? [...digits].map((digit) => digit + digit).join('') : digits;

    return [0, 2, 4].map((offset) => parseInt(full.slice(offset, offset + 2), 16));
}

function linearize(channel) {
    const value = channel / CHANNEL_MAX;

    return value <= SRGB_KNEE
        ? value / SRGB_LINEAR_SLOPE
        : ((value + SRGB_OFFSET) / SRGB_SCALE) ** SRGB_GAMMA;
}

/**
 * WCAG 2 relative luminance: 0 for black, 1 for white.
 */
export function relativeLuminance(hex) {
    return parseHex(hex)
        .map(linearize)
        .reduce((sum, channel, index) => sum + channel * LUMINANCE_WEIGHTS[index], 0);
}

/**
 * WCAG 2 contrast ratio between two colours, from 1 to 21, whatever their order.
 */
export function contrastRatio(hexA, hexB) {
    const [lighter, darker] = [relativeLuminance(hexA), relativeLuminance(hexB)].sort((a, b) => b - a);

    return (lighter + FLARE) / (darker + FLARE);
}

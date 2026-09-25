import { colorForTheme, UnknownWowColorError } from '../utils/wowColors';
import { useTheme } from './useTheme';

export function useWowColor() {
    const { effective } = useTheme();

    // Text in a game colour must use the variant that stays readable on the current theme.
    const readable = (color) => (color ? colorForTheme(color, effective.value) : undefined);

    // Blizzard sends values the module does not know (a neutral faction, a new class):
    // the screen shows them uncoloured rather than failing.
    function safe(lookup, value) {
        try {
            return lookup(value);
        } catch (error) {
            if (error instanceof UnknownWowColorError) {
                return null;
            }
            throw error;
        }
    }

    return { readable, safe };
}

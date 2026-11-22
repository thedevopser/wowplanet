// The server names the PvP brackets in French: the sheet names them again from their slug.
const NAMED_BRACKETS = Object.freeze(['2v2', '3v3', 'rbg']);
const SPEC_BRACKET = /^(shuffle|blitz)-(.+)$/;
const OVERALL = 'overall';
const SEPARATOR = ' — ';

export const pvpGroupLabel = (group, t) => t(`pvp.group.${group.key}`);

export function pvpBracketLabel(bracket, t) {
    if (NAMED_BRACKETS.includes(bracket.slug)) {
        return t(`pvp.bracket.${bracket.slug}`);
    }

    const specBracket = SPEC_BRACKET.exec(bracket.slug);
    if (!specBracket) {
        return bracket.label;
    }

    const [, group, tail] = specBracket;
    const short = bracket.spec ?? (tail === OVERALL ? t('pvp.all_specs') : bracket.label.split(SEPARATOR).at(-1));

    return `${t(`pvp.group.${group}`)}${SEPARATOR}${short}`;
}

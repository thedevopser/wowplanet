import { DARK_THEME } from './themeTokens';
import { classColor, dimensionColor, rankColor, UnknownWowColorError } from './wowColors';

const WIDTH = 700;
// Drawn at twice its size so that the image stays sharp on dense screens and once shared.
const SCALE = 2;
const ROW_H = 30;
// Height of the card without its dimension rows.
const CHROME_H = 220;
const FONT = 'system-ui, -apple-system, sans-serif';
const OUT_OF_GAP = 8;
const OUT_OF_BASELINE_OFFSET = 24;
const BADGE_FILL_ALPHA = 0.15;
const FOOTER_ALPHA = 0.7;

// The card is always dark: game colours take their variant readable on night.
function onDark(lookup, value, fallback) {
    try {
        return lookup(value).onDark;
    } catch (error) {
        if (error instanceof UnknownWowColorError) return fallback;
        throw error;
    }
}

function roundRect(ctx, x, y, w, h, r) {
    if (ctx.roundRect) {
        ctx.beginPath();
        ctx.roundRect(x, y, w, h, r);
    } else {
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.arcTo(x + w, y, x + w, y + h, r);
        ctx.arcTo(x + w, y + h, x, y + h, r);
        ctx.arcTo(x, y + h, x, y, r);
        ctx.arcTo(x, y, x + w, y, r);
        ctx.closePath();
    }
}

export function renderScoreCard({ variant, characterName, characterRealm, characterClass, characterRace, characterLevel, classId, characterCount, globalScore, rank, dimensions }) {
    const rows = Array.isArray(dimensions) ? dimensions.filter(d => d.applicable) : [];
    const HEIGHT = CHROME_H + rows.length * ROW_H;

    const canvas = document.createElement('canvas');
    canvas.width = WIDTH * SCALE;
    canvas.height = HEIGHT * SCALE;
    const ctx = canvas.getContext('2d');
    ctx.scale(SCALE, SCALE);

    const bgGrad = ctx.createLinearGradient(0, 0, WIDTH, HEIGHT);
    bgGrad.addColorStop(0, DARK_THEME['surface-raised']);
    bgGrad.addColorStop(1, DARK_THEME.background);
    ctx.fillStyle = bgGrad;
    ctx.fillRect(0, 0, WIDTH, HEIGHT);

    ctx.fillStyle = DARK_THEME.accent;
    ctx.fillRect(0, 0, WIDTH, 3);

    ctx.font = `bold 13px ${FONT}`;
    ctx.fillStyle = DARK_THEME.accent;
    ctx.textBaseline = 'top';
    ctx.fillText('WOWPLANET', 24, 16);

    ctx.font = `500 12px ${FONT}`;
    ctx.fillStyle = DARK_THEME['text-muted'];
    ctx.textAlign = 'right';
    ctx.fillText('Score de complétion', WIDTH - 24, 17);
    ctx.textAlign = 'left';

    const infoY = 44;
    if (variant === 'personal') {
        ctx.font = `bold 18px ${FONT}`;
        ctx.fillStyle = onDark(classColor, classId, DARK_THEME.text);
        ctx.fillText(characterName || '', 24, infoY);

        const nameWidth = ctx.measureText(characterName || '').width;
        ctx.font = `400 13px ${FONT}`;
        ctx.fillStyle = DARK_THEME['text-muted'];
        ctx.fillText(` — ${characterRace || ''} ${characterClass || ''} ${characterLevel || ''} | ${characterRealm || ''}`, 24 + nameWidth, infoY + 3);
    } else {
        ctx.font = `bold 18px ${FONT}`;
        ctx.fillStyle = DARK_THEME.text;
        ctx.fillText('Score du compte', 24, infoY);

        ctx.font = `400 13px ${FONT}`;
        ctx.fillStyle = DARK_THEME['text-subtle'];
        ctx.fillText(`${characterCount || 0} personnage${(characterCount || 0) > 1 ? 's' : ''} analysé${(characterCount || 0) > 1 ? 's' : ''}`, 24, infoY + 24);
    }

    const scoreY = variant === 'personal' ? 85 : 95;
    const rankHex = onDark(rankColor, rank, DARK_THEME.text);

    const scoreText = String(globalScore ?? 0);
    const outOfText = '/ 100';
    const scoreFont = `900 52px ${FONT}`;
    const outOfFont = `bold 20px ${FONT}`;

    ctx.font = scoreFont;
    const scoreWidth = ctx.measureText(scoreText).width;
    ctx.font = outOfFont;
    const outOfWidth = ctx.measureText(outOfText).width;
    const scoreX = (WIDTH - scoreWidth - OUT_OF_GAP - outOfWidth) / 2;

    ctx.font = scoreFont;
    ctx.fillStyle = rankHex;
    ctx.fillText(scoreText, scoreX, scoreY);

    ctx.font = outOfFont;
    ctx.fillStyle = DARK_THEME['text-subtle'];
    ctx.fillText(outOfText, scoreX + scoreWidth + OUT_OF_GAP, scoreY + OUT_OF_BASELINE_OFFSET);

    const badgeY = scoreY + 62;
    const rankText = (rank || '').toUpperCase();
    ctx.font = `800 11px ${FONT}`;
    const badgeW = ctx.measureText(rankText).width + 24;
    const badgeX = WIDTH / 2 - badgeW / 2;

    roundRect(ctx, badgeX, badgeY, badgeW, 24, 12);
    ctx.fillStyle = rankHex;
    ctx.globalAlpha = BADGE_FILL_ALPHA;
    ctx.fill();
    ctx.globalAlpha = 1;
    ctx.strokeStyle = rankHex;
    ctx.lineWidth = 1;
    ctx.stroke();

    ctx.fillStyle = rankHex;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(rankText, WIDTH / 2, badgeY + 12);

    ctx.textAlign = 'left';
    ctx.textBaseline = 'top';

    const startY = badgeY + 42;
    const labelX = 24;
    const barX = 160;
    const barW = 320;
    const barH = 12;
    const pctX = barX + barW + 14;
    const detailX = pctX + 50;

    for (let i = 0; i < rows.length; i++) {
        const dim = rows[i];
        const y = startY + i * ROW_H;
        const pct = Math.round(dim.score || 0);
        const color = onDark(dimensionColor, dim.key, DARK_THEME['text-muted']);

        ctx.font = `600 13px ${FONT}`;
        ctx.fillStyle = DARK_THEME.text;
        ctx.fillText(dim.label, labelX, y);

        roundRect(ctx, barX, y + 1, barW, barH, 6);
        ctx.fillStyle = DARK_THEME['surface-raised'];
        ctx.fill();

        if (pct > 0) {
            const fillW = Math.max(12, (pct / 100) * barW);
            roundRect(ctx, barX, y + 1, fillW, barH, 6);
            ctx.fillStyle = color;
            ctx.fill();
        }

        ctx.font = `bold 13px ${FONT}`;
        ctx.fillStyle = color;
        ctx.fillText(`${pct}%`, pctX, y);

        ctx.font = `400 10px ${FONT}`;
        ctx.fillStyle = DARK_THEME['text-subtle'];
        const completed = dim.completed?.toLocaleString('fr-FR') ?? '0';
        const total = dim.total?.toLocaleString('fr-FR') ?? '0';
        ctx.fillText(`${completed}/${total}`, detailX, y + 2);
    }

    ctx.font = `500 11px ${FONT}`;
    ctx.fillStyle = DARK_THEME.accent;
    ctx.globalAlpha = FOOTER_ALPHA;
    ctx.textAlign = 'center';
    ctx.fillText('wowplanet.fr', WIDTH / 2, HEIGHT - 18);
    ctx.globalAlpha = 1;

    ctx.fillStyle = DARK_THEME.accent;
    ctx.fillRect(0, HEIGHT - 3, WIDTH, 3);

    return canvas;
}

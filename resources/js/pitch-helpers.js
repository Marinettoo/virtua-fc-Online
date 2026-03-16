/**
 * Shared pitch rendering utilities.
 * Used by both lineup.js (pre-match) and live-match.js (in-match).
 */

export function getInitials(name) {
    if (!name) return '??';
    const parts = name.trim().split(/\s+/);
    if (parts.length === 1) {
        return parts[0].substring(0, 2).toUpperCase();
    }
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

/**
 * Generate inline CSS for the player badge background based on team shirt.
 * @param {string} role - Position group (e.g. 'Goalkeeper', 'Defender')
 * @param {object|null} teamColors - { primary, secondary, number, pattern }
 */
export function getShirtStyle(role, teamColors) {
    if (role === 'Goalkeeper') {
        return 'background: linear-gradient(to bottom right, #FBBF24, #D97706)';
    }

    const tc = teamColors;
    if (!tc) return 'background: linear-gradient(to bottom right, #3B82F6, #1D4ED8)';

    const p = tc.primary;
    const s = tc.secondary;

    switch (tc.pattern) {
        case 'stripes':
            return `background: linear-gradient(90deg, ${s} 3px, ${p} 3px, ${p} 9px, ${s} 9px); background-size: 12px 100%; background-position: center`;
        case 'hoops':
            return `background: linear-gradient(0deg, ${s} 3px, ${p} 3px, ${p} 9px, ${s} 9px); background-size: 100% 12px; background-position: center`;
        case 'sash':
            return `background: linear-gradient(135deg, ${p} 0%, ${p} 35%, ${s} 35%, ${s} 65%, ${p} 65%, ${p} 100%)`;
        case 'bar':
            return `background: linear-gradient(90deg, ${p} 0%, ${p} 35%, ${s} 35%, ${s} 65%, ${p} 65%, ${p} 100%)`;
        case 'halves':
            return `background: linear-gradient(90deg, ${p} 50%, ${s} 50%)`;
        default:
            return `background: ${p}`;
    }
}

/**
 * Get the complete inline style for the player number including backdrop for patterned shirts.
 * @param {string} role - Position group
 * @param {object|null} teamColors - { primary, secondary, number, pattern }
 */
export function getNumberStyle(role, teamColors) {
    if (role === 'Goalkeeper') {
        return 'color: #FFFFFF; text-shadow: 0 1px 2px rgba(0,0,0,0.5)';
    }
    const tc = teamColors;
    if (!tc) return 'color: #FFFFFF; text-shadow: 0 1px 2px rgba(0,0,0,0.5)';

    const color = tc.number || '#FFFFFF';

    if (tc.pattern !== 'solid') {
        const backdrop = _getBackdropColor(tc);
        return `color: ${color}; background: ${backdrop}CC; text-shadow: 0 1px 2px rgba(0,0,0,0.15)`;
    }

    return `color: ${color}; text-shadow: 0 1px 2px rgba(0,0,0,0.2)`;
}

/**
 * Pick the team color (primary or secondary) that best contrasts with the number color.
 */
function _getBackdropColor(tc) {
    const numLum = _hexLuminance(tc.number);
    const priLum = _hexLuminance(tc.primary);
    const secLum = _hexLuminance(tc.secondary);
    return Math.abs(numLum - priLum) >= Math.abs(numLum - secLum) ? tc.primary : tc.secondary;
}

function _hexLuminance(hex) {
    if (!hex || hex.length < 7) return 0.5;
    const r = parseInt(hex.slice(1, 3), 16) / 255;
    const g = parseInt(hex.slice(3, 5), 16) / 255;
    const b = parseInt(hex.slice(5, 7), 16) / 255;
    return 0.299 * r + 0.587 * g + 0.114 * b;
}

/**
 * Convert grid cell coordinates to pitch percentage coordinates.
 * @param {number} col - Column index (0-based)
 * @param {number} row - Row index (0-based)
 * @param {number} gridCols - Total columns in grid
 * @param {number} gridRows - Total rows in grid
 * @returns {{ x: number, y: number }} Percentage coordinates
 */
export function cellToCoords(col, row, gridCols, gridRows) {
    return {
        x: col * (100 / gridCols) + (100 / (gridCols * 2)),
        y: row * (100 / gridRows) + (100 / (gridRows * 2)),
    };
}

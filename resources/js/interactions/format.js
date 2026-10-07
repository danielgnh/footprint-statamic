// Below this many views a share means little, so the tab shows counts instead.
export const SMALL_SAMPLE = 30;

// The frame each device's data is drawn on: its real width, so media queries match.
export const DEVICES = {
    mobile: { label: 'Mobile', width: 390, height: 844 },
    tablet: { label: 'Tablet', width: 820, height: 1180 },
    desktop: { label: 'Desktop', width: 1440, height: 900 },
};

const numbers = new Intl.NumberFormat();

export function number(value) {
    return numbers.format(value ?? 0);
}

export function percent(share) {
    return `${Math.round((share ?? 0) * 100)}%`;
}

// A share of the page's views, or the views it stands for when the sample is small.
export function share(measure, views) {
    return views < SMALL_SAMPLE ? number(measure?.views) : percent(measure?.share);
}

export function duration(ms) {
    const seconds = Math.round((ms ?? 0) / 1000);

    if (seconds < 60) return `${seconds} s`;

    const minutes = Math.floor(seconds / 60);

    return seconds % 60 === 0 ? `${minutes} min` : `${minutes} min ${seconds % 60} s`;
}

export function day(iso) {
    return iso ? new Date(iso).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }) : '';
}

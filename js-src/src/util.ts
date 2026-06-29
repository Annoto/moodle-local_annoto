import { IMoodleRelease } from './interfaces';

export const parseMoodleVersion = (release?: string): IMoodleRelease => {
    const version = release?.split(' ')[0];
    const parts = (version || '').split('.').map((v) => parseInt(v, 10));
    // parseInt yields NaN for missing/invalid parts, and `typeof NaN === 'number'`,
    // so the original typeof guard never caught them. Coerce each part to 0
    // individually so a partial release (e.g. "4.4dev") still keeps major/minor.
    const toInt = (v: number): number => (Number.isNaN(v) ? 0 : v);
    return { major: toInt(parts[0]), minor: toInt(parts[1]), patch: toInt(parts[2]) };
};

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export const debounce = (func: (...args: any[]) => void, wait = 0): ((...args: any[]) => void) => {
    let timer: ReturnType<typeof setTimeout>;
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    return (...args: any[]): void => {
        clearTimeout(timer);
        timer = setTimeout(func, wait, ...args);
    };
};

export const delay = (ms: number): Promise<void> =>
    new Promise((resolve) => {
        setTimeout(resolve, ms);
    });

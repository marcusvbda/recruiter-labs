import type { ReactNode } from 'react';

type BtnTestProps = {
    onClick: () => void;
    children: ReactNode;
};

export function BtnTest({ onClick, children }: BtnTestProps) {
    return (
        <button
            onClick={onClick}
            className="rounded-2xl bg-amber-800 p-4 text-center text-white"
        >
            {children}
        </button>
    );
}

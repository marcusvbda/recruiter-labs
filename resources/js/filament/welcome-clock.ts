class WelcomeClock extends HTMLElement {
    private intervalId?: number;

    connectedCallback(): void {
        this.update();
        this.intervalId = window.setInterval(() => this.update(), 1000);
    }

    disconnectedCallback(): void {
        if (this.intervalId !== undefined) {
            window.clearInterval(this.intervalId);
        }
    }

    private update(): void {
        const currentDate = new Date();
        const locale =
            this.dataset.locale || document.documentElement.lang || 'en';
        const timeElement =
            this.querySelector<HTMLTimeElement>('[data-clock-time]');
        const dateElement =
            this.querySelector<HTMLElement>('[data-clock-date]');
        const timezoneElement = this.querySelector<HTMLElement>(
            '[data-clock-timezone]',
        );
        const greetingElement = document.querySelector<HTMLElement>(
            '[data-clock-greeting]',
        );

        if (timeElement) {
            timeElement.dateTime = currentDate.toISOString();
            timeElement.textContent = new Intl.DateTimeFormat(locale, {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
            }).format(currentDate);
        }

        if (dateElement) {
            dateElement.textContent = new Intl.DateTimeFormat(locale, {
                weekday: 'long',
                day: '2-digit',
                month: 'long',
                year: 'numeric',
            }).format(currentDate);
        }

        if (timezoneElement) {
            timezoneElement.textContent =
                Intl.DateTimeFormat(locale, {
                    timeZoneName: 'long',
                })
                    .formatToParts(currentDate)
                    .find((part) => part.type === 'timeZoneName')?.value ?? '';
        }

        if (greetingElement) {
            greetingElement.textContent = this.greetingForHour(
                currentDate.getHours(),
                greetingElement,
            );
        }
    }

    private greetingForHour(hour: number, element: HTMLElement): string {
        if (hour < 12) {
            return element.dataset.goodMorning ?? '';
        }

        if (hour < 18) {
            return element.dataset.goodAfternoon ?? '';
        }

        return element.dataset.goodEvening ?? '';
    }
}

if (!customElements.get('welcome-clock')) {
    customElements.define('welcome-clock', WelcomeClock);
}

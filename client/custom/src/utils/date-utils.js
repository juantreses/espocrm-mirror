define('custom:utils/date-utils', [], function () {

    function toOffsetISOString(date) {
        const tzOffset = -date.getTimezoneOffset(); // in minutes
        const localTime = new Date(date.getTime() + tzOffset * 60000); // shift from UTC → local

        const sign = tzOffset >= 0 ? '+' : '-';
        const pad = n => `${Math.floor(Math.abs(n))}`.padStart(2, '0');
        const hours = pad(tzOffset / 60);
        const minutes = pad(tzOffset % 60);

        return localTime.toISOString().replace('Z', `${sign}${hours}:${minutes}`);
    }

    return {
        toOffsetISOString,
    };
});
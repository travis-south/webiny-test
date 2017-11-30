import Webiny from 'webiny';

class DateTimeModifier {
    getName() {
        return 'datetime';
    }

    execute(value) {
        return Webiny.I18n.datetime(value)
    }
}

export default DateTimeModifier;
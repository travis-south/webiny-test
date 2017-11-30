import Webiny from 'webiny';

class PriceModifier {
    getName() {
        return 'price';
    }

    execute(value) {
        return Webiny.I18n.price(value)
    }
}

export default PriceModifier;
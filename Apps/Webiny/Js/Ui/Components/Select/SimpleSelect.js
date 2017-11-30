import React from 'react';
import ReactDOM from 'react-dom';
import _ from 'lodash';
import $ from 'jquery';
import Webiny from 'webiny';
import ReactDOMServer from 'react-dom/server';


class SimpleSelect extends Webiny.Ui.Component {

    constructor(props) {
        super(props);

        this.select2 = null;
        this.options = null;
        this.bindMethods('getConfig,getValue,triggerChange,getSelect2InputElement,itemRenderer,getCurrentData,getPreviousData');
    }

    componentDidMount() {
        super.componentDidMount();
        if (!this.isRendered()) {
            return;
        }
        this.instantiate();
    }

    componentWillReceiveProps(props) {
        super.componentWillReceiveProps(props);
        if (props.value !== this.props.value) {
            this.previousData = _.clone(this.getCurrentData());
        }
    }

    componentDidUpdate() {
        super.componentDidUpdate();
        if (!this.isRendered()) {
            this.select2 = null;
            return;
        }

        this.instantiate();
        const possibleValues = _.map(this.options, obj => obj.id.toString());
        const value = this.getValue();
        const inPossibleValues = possibleValues.indexOf(value) > -1;

        if (!this.options || !_.isEqual(this.props.options, this.options)) {
            this.select2.empty();
            this.getSelect2InputElement().select2(this.getConfig(this.props));
            setTimeout(() => {
                this.select2.val(this.getValue()).trigger('change');
            }, 100);
        }

        $(ReactDOM.findDOMNode(this)).find('select').prop('disabled', !!this.props.disabled);

        if (value !== null && !inPossibleValues && possibleValues.length > 0) {
            this.triggerChange(null);
            return;
        }

        if (value !== null && inPossibleValues) {
            this.select2.val(value).trigger('change');
            return;
        }

        // Select first value if model is empty and "autoSelectFirstOptionOption" is enabled
        if (value === null && this.props.autoSelectFirstOption) {
            const firstValue = _.get(this.props.options, '0.id');
            if (firstValue) {
                this.triggerChange(firstValue);
            }
            return;
        }

        this.select2.val('').trigger('change');
    }

    instantiate() {
        if (!this.select2) {
            this.select2 = this.getSelect2InputElement().select2(this.getConfig(this.props));
            this.select2.on('select2:select', e => {
                this.triggerChange(e.target.value);
            });
            this.select2.on('select2:unselect', () => {
                this.triggerChange(null);
            });
            this.select2.val(this.getValue()).trigger('change');

            if (this.props.dropdownClassName) {
                setTimeout(() => this.select2.data('select2').$dropdown.addClass(this.props.dropdownClassName));
            }
        }
    }

    getSelect2InputElement() {
        return $(ReactDOM.findDOMNode(this)).find('select');
    }

    getValue() {
        const value = this.props.value;
        if (value === null || value === undefined) {
            return value;
        }

        return _.isObject(value) ? value.id : '' + value;
    }

    getCurrentData() {
        if (this.props.useDataAsValue) {
            return this.props.value;
        }

        let data = null;
        const option = _.find(this.options, {id: this.props.value});
        if (option) {
            data = option.data;
        }

        return this.props.value ? data : null;
    }

    getPreviousData() {
        return this.previousData;
    }

    triggerChange(value) {
        if (this.props.useDataAsValue && value) {
            const selectedOption = _.find(this.options, {id: value});
            if (!selectedOption.data) {
                console.warn('Warning: attempting to use item data but data is not present in option items!');
            } else {
                value = selectedOption.data;
            }
        }

        // Save previous selection data so it can be accessed from onChange handlers
        const prevValue = this.getValue();
        if (this.props.useDataAsValue) {
            this.previousData = prevValue ? _.clone(prevValue) : null;
        } else {
            let data = null;
            const option = _.find(this.options, {id: prevValue});
            if (option) {
                data = option.data;
            }
            this.previousData = data ? _.clone(data) : null;
        }
        this.props.onChange(value);
    }

    getOptionText(text) {
        if (!text) {
            return '';
        }

        if (text.startsWith('<')) {
            return $(text);
        }

        return text || '';
    }

    /**
     * This will be triggered twice due to a bug in Select2 (https://github.com/select2/select2/pull/4306)
     * @param item
     * @param type optionRenderer || selectedRenderer
     * @returns {*}
     */
    itemRenderer(item, type) {
        let text = item.text;
        if (_.isFunction(this.props[type]) && item.id) {
            text = this.props[type]({option: item || {}});
        }

        if (text && !_.isString(text)) {
            text = ReactDOMServer.renderToStaticMarkup(text);
        }

        return this.getOptionText(text);
    }

    getConfig(props) {
        const config = {
            disabled: props.disabled,
            minimumResultsForSearch: props.minimumResultsForSearch,
            minimumInputLength: props.minimumInputLength,
            placeholder: props.placeholder,
            allowClear: props.allowClear,
            templateResult: item => this.itemRenderer(item, 'optionRenderer'),
            templateSelection: item => this.itemRenderer(item, 'selectedRenderer')
        };

        if (_.isFunction(this.props.matcher)) {
            config.matcher = (params, data) => {
                const term = (params.term || '').trim();
                if (!term || term.length === 0) {
                    return data;
                }
                return this.props.matcher({term, option: data});
            };
        }

        if (this.props.dropdownParent) {
            config['dropdownParent'] = $(ReactDOM.findDOMNode(this)).find(props.dropdownParent);
        }

        if (!this.options || !_.isEqual(props.options, this.options) || !this.select2) {
            // Prepare options
            const options = [];
            _.each(props.options, option => {
                if (React.isValidElement(option.text)) {
                    option.text = ReactDOMServer.renderToStaticMarkup(option.text);
                }
                options.push(option);
            });
            config['data'] = this.options = options;
        }

        return config;
    }
}

SimpleSelect.defaultProps = {
    value: null,
    allowClear: false,
    autoSelectFirstOption: false,
    placeholder: null,
    onChange: _.noop,
    minimumInputLength: 0,
    minimumResultsForSearch: 15,
    useDataAsValue: false,
    dropdownParent: '.dropdown-wrapper',
    dropdownClassName: null,
    optionRenderer: null,
    selectedRenderer: null,
    matcher: null,
    renderer() {
        return (
            <div className="inputGroup">
                <select style={{'width': '100%'}}/>

                <div className="dropdown-wrapper"/>
            </div>
        );
    }
};

export default Webiny.createComponent(SimpleSelect, {
    modules: ['Webiny/Vendors/Select2'],
    api: ['getCurrentData', 'getPreviousData']
});
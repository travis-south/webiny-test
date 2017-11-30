import React from 'react';
import Webiny from 'webiny';
import _ from 'lodash';
import Step from './Step';
import styles from './styles.scss';

/**
 * Wizard component, makes it easier to create wizards, without worrying about common features like steps, navigation, content etc.
 */
class Container extends Webiny.Ui.Component {
    constructor(props) {
        super(props);
        this.state = {
            steps: {current: props.initialStep},
            loading: false
        };

        this.form = props.form;

        this.bindMethods('setStep', 'nextStep', 'previousStep');
    }

    /**
     * Let's just call onStart callback.
     */
    componentDidMount() {
        super.componentDidMount();
        this.props.onStart(this.getCallbackParams());

        const next = this.getCurrentStep();
        next.onEnter(this.getCallbackParams({previous: null, next}));
    }

    /**
     * Returns total count of all valid steps.
     * @returns {number}
     */
    countSteps() {
        let count = 0;
        const components = this.props.children(this.getCallbackParams());
        React.Children.forEach(components.props.children, component => Webiny.isElementOfType(component, Step) && count++);

        return count;
    }

    /**
     * Returns index of current step
     * @returns {number}
     */
    getCurrentStepIndex() {
        return this.state.steps.current;
    }

    getCurrentStep() {
        return this.parseSteps(this.getCurrentStepIndex());
    }

    /**
     * Tells us whether current step is the first one or not.
     * @returns {boolean}
     */
    isFirstStep() {
        return this.getCurrentStepIndex() === 0;
    }

    /**
     * Tells us whether current step is the last one or not.
     * @returns {boolean}
     */
    isLastStep() {
        const lastIndex = this.countSteps() - 1;
        return this.getCurrentStepIndex() === lastIndex;
    }

    /**
     * Parses all steps passed as immediate children to the Wizard component.
     * If index is passed, only that parsed step will be returned.
     * @returns {Object}
     */
    parseSteps(index = null) {
        const output = [];
        const components = this.props.children(this.getCallbackParams());

        if (index === null) {
            React.Children.forEach(components.props.children, (component, index) => {
                if (Webiny.isElementOfType(component, Step)) {
                    output.push(this.parseStep(component, index));
                }
            });
            return output;
        }

        const component = components.props.children[index];
        if (Webiny.isElementOfType(component, Step)) {
            return this.parseStep(component, index);
        }

        return null;
    }

    /**
     * Parses given step, a React component, into a JSON object.
     * @param step
     * @param index
     */
    parseStep(step, index) {
        const output = _.assign(
            _.pick(step.props, _.keys(Step.defaultProps)),
            {
                index,
                current: index === this.getCurrentStepIndex(),
                completed: index < this.getCurrentStepIndex(),
                actions: [],
                content: null
            });

        React.Children.forEach(step.props.children, component => {
            if (Webiny.isElementOfType(component, Step.Content)) {
                output.content = component.props.children;
                return;
            }

            if (Webiny.isElementOfType(component, Step.Actions)) {
                React.Children.forEach(component.props.children, (action, actionIndex) => {
                    if (React.isValidElement(action)) {
                        output.actions.push(React.cloneElement(action, _.assign({}, action.props, {key: actionIndex, wizard: this})));
                    }
                });
            }
        });

        return output;
    }

    /**
     * For easier passing of callback params.
     * @param merge
     * @returns {{wizard: Container, form: *, model: *}}
     */
    getCallbackParams(merge) {
        const output = {wizard: this, form: this.form, model: this.form.getModel()};
        return merge ? _.assign(output, merge) : output;
    }

    /**
     * Sets current step.
     * @param index
     * @returns {Promise.<void>}
     */
    async setStep(index) {
        const steps = this.parseSteps();

        const previous = steps[this.getCurrentStepIndex()];
        const next = steps[index];

        this.setState({loading: true});

        const params = this.getCallbackParams({previous, next});
        previous && await previous.onLeave(params);

        await this.props.onTransition(params);

        this.setState(state => {
            state.loading = false;
            state.steps.current = index;
            return state;
        }, () => next.onEnter(params));
    }

    /**
     * Switches to next step.
     */
    nextStep() {
        if (this.isLastStep()) {
            return;
        }
        this.setStep(this.getCurrentStepIndex() + 1);
    }

    /**
     * Switches to previous step.
     */
    previousStep() {
        if (this.isFirstStep()) {
            return;
        }
        this.setStep(this.getCurrentStepIndex() - 1);
    }

    /**
     * Called when wizard is finished so additional actions can be made if needed.
     * @returns {Promise.<void>}
     */
    async finish() {
        this.setState({loading: true});
        await this.props.onFinish(this.getCallbackParams());
        this.setState({loading: false});
    }
}

Container.defaultProps = {
    form: null,
    initialStep: 0,
    onTransition: _.noop,
    onFinish: _.noop,
    onStart: _.noop,
    navigationRenderer(params) {
        return (
            <Webiny.Ui.LazyLoad modules={['Icon']}>
                {({Icon}) => (
                    <ul className={params.styles.navigation}>
                        {params.steps.list.map((step, index) => (
                            <li key={index}
                                className={params.wizard.classSet((step.completed ? params.styles.completed : null), (step.current ? params.styles.current : null))}>
                                <div>
                                    {step.completed ? (
                                        <Icon type="success" icon="icon-check" className="animated rotateIn"/>
                                    ) : (
                                        <span>{step.index + 1}</span>
                                    )}
                                </div>
                                <label>{step.title}</label>
                            </li>
                        ))}
                    </ul>
                )}
            </Webiny.Ui.LazyLoad>
        );
    },
    contentRenderer(params) {
        return (
            <div className={params.styles.content}>
                {params.steps.current.content}
            </div>
        );
    },
    actionsRenderer(params) {
        return (
            <div className={params.styles.actions}>
                {params.steps.current.actions}
            </div>
        );
    },
    loaderRenderer({wizard}) {
        const {Loader} = wizard.props;
        return wizard.state.loading && <Loader/>;
    },
    layoutRenderer({loader, navigation, content, actions, styles}) {
        return (
            <webiny-wizard className={styles.container}>
                {loader}
                {navigation}
                {content}
                {actions}
            </webiny-wizard>
        );
    },
    renderer() {
        const params = this.getCallbackParams({steps: {list: [], current: null}});
        const {styles} = this.props;

        params.steps.list = this.parseSteps();
        params.steps.current = params.steps.list[this.getCurrentStepIndex()];
        params.styles = styles;


        return this.props.layoutRenderer(_.assign(params, {
            navigation: this.props.navigationRenderer(params),
            content: this.props.contentRenderer(params),
            actions: this.props.actionsRenderer(params),
            loader: this.props.loaderRenderer(params)
        }));
    }
};

export default Webiny.createComponent(Container, {styles});
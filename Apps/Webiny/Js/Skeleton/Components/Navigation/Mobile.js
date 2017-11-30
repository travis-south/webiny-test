import React from 'react';
import Webiny from 'webiny';
import _ from 'lodash';
import utils from './utils';
import styles from './styles.scss';

class Mobile extends Webiny.Ui.Component {
    constructor(props) {
        super(props);

        this.state = {};

        this.bindMethods('onClick');

        /**
         * Menu renderer passed to <Menu>.
         * Note that `this` is still bound to `Mobile` class since we are passing an arrow function.
         */
        this.renderer = (menu) => {
            const props = _.clone(menu.props);
            if (!utils.canAccess(props)) {
                return null;
            }

            const {level} = props;
            const {Link} = this.props;
            const children = React.Children.toArray(props.children);
            const hasChildren = children.length > 0;

            const menuIconClass = this.classSet('icon app-icon', {'fa': _.includes(props.icon, 'fa-')}, props.icon);

            const linkProps = {
                key: props.id,
                label: props.label,
                onClick: this.closeMobileMenu,
                className: props.level === 0 && props.apps.includes(this.props.highlight) ? styles.animateFlicker : null,
                children: [
                    props.icon ? <span key="icon" className={menuIconClass}/> : null,
                    level > 1 ? props.label : <span key="title" className="app-title">{props.label}</span>,
                    hasChildren ? <span key="caret" className="icon icon-caret-down mobile-caret"/> : null
                ]
            };

            let childMenuItems = null;
            if (hasChildren) {
                // Build array of child items and check their access roles.
                childMenuItems = children.map((child, i) => {
                    if (!utils.canAccess(child.props)) {
                        return null;
                    }

                    return React.cloneElement(child, {key: i, renderer: this.renderer});
                });

                // If no child items are there to render - hide parent menu as well.
                if (!childMenuItems.filter(item => !_.isNil(item)).length) {
                    return null;
                }
                linkProps.onClick = e => this.onClick(menu, e);
            }

            const className = this.classSet({
                open: this.state[props.id],
                active: props.level === 0 ? utils.findRoute(props, Webiny.Router.getActiveRoute().getName()) : false
            });

            return (
                <li className={className} key={props.id}>
                    {utils.getLink(props.route, Link, linkProps)}
                    {hasChildren && (
                        <ul className={'level-' + (level + 1)}>
                            <li className="main-menu--close back" onClick={e => this.onClick(menu, e)}>Go Back</li>
                            {childMenuItems}
                        </ul>
                    )}
                </li>
            );
        };
    }

    onClick(menu, e) {
        e.stopPropagation();
        const state = this.state;
        state[menu.props.id] = !_.get(state, menu.props.id, false);
        this.setState(state);
        this.props.onClick(menu);
    }

    closeMobileMenu() {
        $('body').removeClass('mobile-nav');
    }
}

Mobile.defaultProps = {
    renderer() {
        return (
            <div className="navigation">
                <div className="shield"/>
                <div className="main-menu">
                    <ul className="menu-list level-0">
                        <li className="main-menu--close" onClick={this.closeMobileMenu}>Close</li>
                        {Webiny.Menu.getMenu().map(menu => (
                            React.cloneElement(menu, {
                                key: menu.props.id,
                                renderer: this.renderer
                            })
                        ))}
                    </ul>
                </div>
            </div>
        );
    }
};

export default Webiny.createComponent(Mobile, {modules: ['Link']});
import React from 'react';
import _ from 'lodash';
import Webiny from 'webiny';
import styles from './styles.css';

class Icon extends Webiny.Ui.Component {

}

Icon.defaultProps = {
    icon: null,
    className: null,
    element: 'span', // span || i
    type: 'default',
    size: 'default',
    renderer() {
        const {styles, icon, className, element, onClick} = this.props;
        let iconSet = 'icon';
        if (_.includes(icon, 'fa-')) {
            iconSet = 'fa icon';
        }

        const typeClasses = {
            default: '',
            danger: styles.danger,
            success: styles.success,
            info: styles.info,
            warning: styles.warning,
        };

        const sizeClasses = {
            default: '',
            '2x': styles.size2x,
            '3x': styles.size3x,
            '4x': styles.size4x
        };

        const classes = this.classSet(iconSet, icon, className, sizeClasses[this.props.size], typeClasses[this.props.type]);

        return React.createElement(element, {className: classes, onClick});
    }
};

export default Webiny.createComponent(Icon, {styles});
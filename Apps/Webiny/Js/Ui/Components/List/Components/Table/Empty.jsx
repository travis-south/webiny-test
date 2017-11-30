import React from 'react';
import _ from 'lodash';
import Webiny from 'webiny';
import styles from '../../styles.css';

/**
 * @i18n.namespace Webiny.Ui.List.Table.Empty
 */
class Empty extends Webiny.Ui.Component {

}

Empty.defaultProps = {
    message: (
        <div className={styles.emptyContainer}>
            <div className={styles.content}>
                <h2>{Webiny.I18n('Sorry, but no records matched your query.')}</h2>

                <p>{Webiny.I18n('Try changing your search parameters.')}</p>
            </div>
        </div>
    ),
    renderer() {
        if (this.props.children) {
            return <webiny-list-empty>{_.isFunction(this.props.children) ? this.props.children() : this.props.children}</webiny-list-empty>;
        }

        return (
            <webiny-list-empty>{this.props.message}</webiny-list-empty>
        );
    }
};

export default Webiny.createComponent(Empty, {styles});
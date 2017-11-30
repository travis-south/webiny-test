import React from 'react';
import _ from 'lodash';
import Webiny from 'webiny';

class Action extends Webiny.Ui.Component {

}

Action.defaultProps = {
    icon: null,
    onClick: _.noop,
    download: null,
    actions: null,
    data: null,
    hide: false,
    renderer() {
        if (_.isFunction(this.props.hide) && this.props.hide(this.props.data)) {
            return null;
        }

        if (_.isFunction(this.props.children)) {
            return this.props.children.call(this, {
                data: this.props.data,
                actions: this.props.actions,
                $this: this
            });
        }

        const {Icon, Link, DownloadLink} = this.props;
        const icon = this.props.icon ? <Icon icon={this.props.icon}/> : null;

        if (this.props.download) {
            return (
                <DownloadLink download={this.props.download} params={this.props.data}>{icon} {this.props.label}</DownloadLink>
            );
        }

        return (
            <Link
                data={this.props.data}
                onClick={() => this.props.onClick.call(this, {
                    data: this.props.data,
                    actions: this.props.actions,
                    $this: this
                })}>
                {icon}
                {this.props.label}
            </Link>
        );
    }
};

export default Webiny.createComponent(Action, {modules: ['Icon', 'Link', 'DownloadLink']});
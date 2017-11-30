import React from 'react';
import ReactDOM from 'react-dom';
import _ from 'lodash';
import Webiny from 'webiny';
import styles from './styles.css';

// TODO: https://www.npmjs.com/package/react-google-maps

class GoogleMap extends Webiny.Ui.FormComponent {

    constructor(props) {
        super(props);

        this.map = null;
        this.marker = null;
        this.geoCoder = null;
        this.loading = null;

        this.bindMethods('positionMarker,setupMap,search');
    }

    componentDidMount() {
        if (!window.google) {
            return Webiny.Page.loadScript('https://maps.googleapis.com/maps/api/js?key=' + this.props.apiKey).then(() => {
                this.setupMap();
            });
        }

        this.setupMap();
    }

    shouldComponentUpdate(newProps) {
        if (!newProps.value) {
            return false;
        }

        return !_.isEqual(this.props.value, newProps.value);
    }

    componentDidUpdate() {
        if (!this.map) {
            return;
        }

        this.positionMarker();
    }

    setupMap() {
        const lat = _.get(this.props, 'value.lat', this.props.center.lat);
        const lng = _.get(this.props, 'value.lng', this.props.center.lng);

        this.map = new google.maps.Map(ReactDOM.findDOMNode(this).querySelector('[data-role="map"]'), {
            center: new google.maps.LatLng(parseFloat(lat), parseFloat(lng)),
            zoom: this.props.zoom,
            mapTypeId: google.maps.MapTypeId.ROADMAP
        });

        this.marker = new google.maps.Marker({
            map: this.map,
            draggable: true
        });

        if (!this.props.readOnly) {
            google.maps.event.addListener(this.marker, 'dragend', () => {
                this.props.onChange({
                    lat: this.marker.getPosition().lat(),
                    lng: this.marker.getPosition().lng()
                });
            });
        }

        this.positionMarker();
    }

    positionMarker() {
        const lat = _.get(this.props, 'value.lat');
        const lng = _.get(this.props, 'value.lng');

        if (lat && lng) {
            const latLng = new google.maps.LatLng(parseFloat(lat), parseFloat(lng));
            this.map.panTo(latLng);
            this.marker.setMap(this.map);
            this.marker.setPosition(latLng);
        }
    }

    search(query) {
        if (!this.geoCoder) {
            this.geoCoder = new google.maps.Geocoder();
        }

        this.geoCoder.geocode({address: query}, results => {
            if (!_.isEmpty(results)) {
                const location = _.get(results[0], 'geometry.location');
                this.props.onChange({
                    lat: location.lat(),
                    lng: location.lng()
                });
            }
        });
    }
}

GoogleMap.defaultProps = {
    apiKey: null,
    zoom: 4,
    center: {
        lat: 0,
        lng: 0
    },
    readOnly: false,
    style: null,
    value: null,
    onChange: _.noop,
    renderer() {
        const {styles} = this.props;
        return (
            <div className={styles.container} style={this.props.style}>
                <div data-role="map" className={styles.map}>{this.props.children}</div>
            </div>
        );
    }
};

export default Webiny.createComponent(GoogleMap, {styles, api: ['search', 'positionMarker']});
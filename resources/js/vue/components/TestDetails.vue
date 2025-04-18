<template>
  <section v-if="errored">
    <p>{{ cdash.error }}</p>
  </section>
  <section v-else>
    <div v-if="loading">
      <img :src="$baseURL + '/img/loading.gif'">
    </div>
    <div v-else>
      <div id="executiontime">
        <img
          :src="$baseURL + '/img/clock.png'"
          :title="'Average: ' + cdash.test.timemean + ', SD: ' + cdash.test.timestd"
        >
        <span class="builddateelapsed">
          {{ cdash.test.time }}
        </span>
      </div>
      <br>

      <b>Test: </b>
      <a
        id="summary_link"
        :href="$baseURL + '/' + cdash.test.summaryLink"
      >
        {{ cdash.test.test }}
      </a>
      <b :class="cdash.test.statusColor">
        ({{ cdash.test.status }})
      </b>
      <br>

      <b>Build: </b>
      <a
        id="build_link"
        :href="$baseURL + '/build/' + cdash.test.buildid"
      >
        {{ cdash.test.build }}
      </a>
      <a
        id="site_link"
        :href="$baseURL + '/sites/' + cdash.test.siteid"
      >
        ({{ cdash.test.site }})
      </a>
      on {{ cdash.test.buildstarttime }}
      <br>

      <div v-if="cdash.test.update.revision">
        <b>Repository revision: </b>
        <a
          id="revision_link"
          :href="cdash.test.update.revisionurl"
        >
          {{ cdash.test.update.revision }}
        </a>
        <br>
      </div>

      <div v-if="cdash.test.details != ''">
        <b>Test Details: </b>
        {{ cdash.test.details }}
        <br>
      </div>

      <div v-if="cdash.project.showtesttime == 1">
        <br>
        <b>Test Timing: </b>
        <b :class="cdash.test.timeStatusColor">
          {{ cdash.test.timestatus }}
        </b>
        <div v-if="cdash.test.timestatus != 'Passed'">
          This test took longer to complete ({{ cdash.test.time }}) than the threshold allows ({{ cdash.test.threshold }}).
        </div>
      </div>

      <div v-if="cdash.test.labels != ''">
        <b>Labels: </b>
        {{ cdash.test.labels }}
        <br>
      </div>

      <br>

      <!-- Display the measurements -->
      <table id="test_measurement_table">
        <tr v-if="cdash.test.compareimages">
          <th class="measurement">
            Interactive Image
          </th>
          <td>
            <div class="je_compare">
              <img
                v-for="image in cdash.test.compareimages"
                :src="$baseURL + '/image/' + image.imgid"
                :alt="image.role"
              >
            </div>
          </td>
        </tr>

        <tr v-for="image in cdash.test.images">
          <th class="measurement">
            {{ image.role }}
          </th>
          <td>
            <img
              :src="$baseURL + '/image/' + image.imgid"
              :alt="image.role"
            >
          </td>
        </tr>

        <tr v-for="measurement in measurements">
          <th class="measurement">
            {{ measurement.name }}
          </th>
          <td>
            {{ measurement.value }}
          </td>
        </tr>
        <tr v-for="file in files">
          <th class="measurement">
            {{ file.name }}
          </th>
          <td>
            <a
              class="cdash-link"
              :href="$baseURL + '/api/v1/testDetails.php?buildtestid=' + buildtestid+ '&fileid=' + file.fileid"
            >
              <img :src="$baseURL + '/img/package.png'">
            </a>
          </td>
        </tr>
        <tr v-for="link in links">
          <td>
            <a
              class="cdash-link"
              :href="link.value"
            >{{ link.name }}</a>
          </td>
        </tr>
      </table>
      <br>

      <!-- Show command line -->
      <img :src="$baseURL + '/img/console.png'">
      <a
        id="commandlinelink"
        href="#"
        @click="showcommandline = !showcommandline"
      >
        <span v-show="!showcommandline">Show Command Line</span>
        <span v-show="showcommandline">Hide Command Line</span>
      </a>
      <transition name="fade">
        <pre
          v-show="showcommandline"
          id="commandline"
          style="white-space: pre-wrap;"
        >{{ cdash.test.command }}</pre>
      </transition>
      <br>

      <!-- Show environment variables -->
      <div v-if="hasenvironment">
        <img :src="$baseURL + '/img/console.png'">
        <a
          id="environmentlink"
          href="#"
          @click="showenvironment = !showenvironment"
        >
          <span v-show="!showenvironment">Show Environment</span>
          <span v-show="showenvironment">Hide Environment</span>
        </a>
        <transition name="fade">
          <pre
            v-show="showenvironment"
            id="environment"
            style="white-space: pre-wrap;"
          >{{ cdash.test.environment }}</pre>
        </transition>
        <br>
      </div>

      <!-- Pull down menu to see the graphs -->
      <img :src="$baseURL + '/img/graph.png'">
      Display graphs:
      <select
        id="GraphSelection"
        v-model="graphSelection"
        @change="displayGraph()"
      >
        <option value="">
          Select...
        </option>
        <option value="time">
          Test Time
        </option>
        <option value="status">
          Failing/Passing
        </option>
        <option v-for="measurement in numericMeasurements">
          {{ measurement.name }}
        </option>
      </select>
      <br>

      <a
        v-show="rawdatalink != ''"
        :href="rawdatalink"
        target="_blank"
      >
        View Graph Data as JSON
      </a>

      <!-- Graph -->
      <div
        v-show="showgraph"
        id="graph_holder"
      />
      <div id="tooltip" />

      <br>
      <b>Test output</b>
      <pre
        id="test_output"
        v-html="cdash.test.output"
      />

      <div v-for="preformatted_measurement in cdash.test.preformatted_measurements">
        <b>{{ preformatted_measurement.name }}</b>
        <pre v-html="preformatted_measurement.value" />
        <br>
      </div>
    </div>
  </section>
</template>

<script>
import ApiLoader from './shared/ApiLoader';
import QueryParams from './shared/QueryParams';
import TextMutator from './shared/TextMutator';
export default {
  name: 'TestDetails',

  data () {
    return {
      // API results.
      buildtestid: null,
      cdash: {},
      loading: true,
      errored: false,

      showcommandline: false,
      showenvironment: false,
      hasenvironment: false,
      showgraph: false,
      graphSelection: '',
      rawdatalink: '',
    };
  },

  computed: {
    files: function () {
      return this.cdash.test.measurements.filter((measurement) => {
        return measurement.type === 'file';
      });
    },
    links: function () {
      return this.cdash.test.measurements.filter((measurement) => {
        return measurement.type === 'text/link';
      });
    },
    measurements: function () {
      return this.cdash.test.measurements.filter((measurement) => {
        return measurement.type !== 'file' && measurement.type !== 'text/link';
      });
    },
    numericMeasurements: function () {
      return this.cdash.test.measurements.filter((measurement) => {
        return measurement.type.lastIndexOf('numeric/', 0) === 0;
      });
    },
  },

  mounted () {
    this.buildtestid = window.location.pathname.split('/').pop();
    let endpoint_path = `/api/v1/testDetails.php?buildtestid=${this.buildtestid}`;
    this.queryParams = QueryParams.get();
    if ('graph' in this.queryParams) {
      this.graphSelection = this.queryParams.graph;
      endpoint_path += `&graph=${this.graphSelection}`;
    }
    ApiLoader.loadPageData(this, endpoint_path);
  },

  updated: function () {
    this.$nextTick(() => {
      $('.je_compare').je_compare({caption: true});
    });
  },

  methods: {
    postSetup: function(response) {
      this.cdash.test.output = TextMutator.ctestNonXmlCharEscape(this.cdash.test.output);
      this.cdash.test.output = TextMutator.terminalColors(this.cdash.test.output, true);

      for (let i = 0; i < this.cdash.test.preformatted_measurements.length; i++) {
        this.cdash.test.preformatted_measurements[i].value = TextMutator.ctestNonXmlCharEscape(this.cdash.test.preformatted_measurements[i].value);
        this.cdash.test.preformatted_measurements[i].value = TextMutator.terminalColors(this.cdash.test.preformatted_measurements[i].value, true);
      }

      this.queryParams = QueryParams.get();
      if (this.graphSelection) {
        this.displayGraph();
      }

      // eslint-disable-next-line eqeqeq
      if (this.cdash.test.environment != '') {
        this.hasenvironment = true;
      }
    },

    displayGraph: function() {
      if (history.pushState) {
        const graph_query = `?graph=${this.graphSelection}`;
        if (window.location.href.indexOf(graph_query) === -1) {
          // Update query string.
          const newurl = `${window.location.protocol}//${window.location.host}${window.location.pathname}${graph_query}`;
          window.history.pushState({path:newurl},'',newurl);

          // Update menu links.
          this.cdash.menu.current = this.cdash.menu.current.split('?')[0] + graph_query;
          if (this.cdash.menu.previous) {
            this.cdash.menu.previous = this.cdash.menu.previous.split('?')[0] + graph_query;
          }
          if (this.cdash.menu.next) {
            this.cdash.menu.next = this.cdash.menu.next.split('?')[0] + graph_query;
          }
          ApiLoader.$emit('api-loaded', this.cdash);
        }
      }

      const testname = this.cdash.test.test;
      const buildid = this.cdash.test.buildid;
      const measurementname = this.graphSelection;
      if (this.graphSelection === '') {
        this.showgraph = false;
        $('#graph_options').html('');
        return;
      }

      this.showgraph = true;

      let graph_type = '';
      let endpoint_path = `/api/v1/testGraph.php?testname=${testname}&buildid=${buildid}`;
      switch (this.graphSelection) {
      case 'status':
        graph_type = 'status';
        break;
      case 'time':
        graph_type = 'time';
        break;
      default:
        graph_type = 'measurement';
        endpoint_path += `&measurementname=${measurementname}`;
        break;
      }
      endpoint_path += `&type=${graph_type}`;
      this.rawdatalink = this.$baseURL + endpoint_path;

      this.$axios
        .get(endpoint_path)
        .then(response => {
          this.testGraph(response.data, graph_type);
        });
    },

    testGraph: function(response, graph_type) {
      // Isolate buildtestids from the actual data points.
      const buildtestids = {};
      const chart_data = [];
      for (let i = 0; i < response.length; i++) {
        const series = {};
        series.label = response[i].label;
        series.data = [];
        for (let j = 0; j < response[i].data.length; j++) {
          series.data.push([response[i].data[j]['x'], response[i].data[j]['y']]);
          if ('buildtestid' in response[i].data[j]) {
            buildtestids[response[i].data[j]['x']] = response[i].data[j]['buildtestid'];
          }
        }
        chart_data.push(series);
      }

      // Options that are shared by all of our different types of charts.
      const options = {
        grid: {
          backgroundColor: '#fffaff',
          clickable: true,
          hoverable: true,
          hoverFill: '#444',
          hoverRadius: 4,
        },
        pan: { interactive: true },
        zoom: { interactive: true, amount: 1.1 },
        xaxis: {
          mode: 'time',
          timeformat: '%Y/%m/%d %H:%M',
          timeBase: 'milliseconds',
        },
        yaxis: {
          zoomRange: false,
          panRange: false,
        },
      };

      switch (graph_type) {
      case 'status':
        // Circles for passed tests, crosses for failed tests.
        chart_data[0].points = { symbol: 'circle'};
        chart_data[1].points = { symbol: 'cross'};
        options.series = {
          points: {
            show: true,
            radius: 5,
          },
        };
        options.yaxis.ticks = [[-1, 'Failed'], [1, 'Passed']];
        options.yaxis.min = -1.2;
        options.yaxis.max = 1.2;
        options.colors = ['#8aba5a', '#de6868'];
        break;

      case 'time':
        // Show threshold series as a filled area.
        chart_data[1].lines = { fill: true };
        // The lack of a 'break' here is intentional.
        // time & measurement charts share common options.
      case 'measurement':
        options.lines = { show: true };
        options.points = { show: true };
        options.colors = ['#0000FF', '#dba255', '#919733'];
        break;
      }

      // Navigate to other tests on click.
      const vm = this;
      $('#graph_holder').bind('plotclick', (e, pos, item) => {
        if (item) {
          const buildtestid = buildtestids[item.datapoint[0]];
          window.location = `${vm.$baseURL}/test/${buildtestid}?graph=${vm.graphSelection}`;
        }
      });

      const plot = $.plot(
        $('#graph_holder'), chart_data, options);
      const date_formatter = d3.time.format('%b %d, %I:%M:%S %p');

      // Show tooltip on hover.
      $('#graph_holder').bind('plothover', (event, pos, item) => {
        if (item) {
          const x = date_formatter(new Date(item.datapoint[0])),
            y = item.datapoint[1].toFixed(2);

          $('#tooltip').html(
            `<b>${x}</b><br/>${
              item.series.label}: <b>${y}</b>`)
            .css({top: item.pageY+5, left: item.pageX+5})
            .fadeIn(200);
        }
        else {
          $('#tooltip').hide();
        }
      });
    },
  },
};
</script>

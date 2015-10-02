Getting started
===============

Example cms.xml:

```xml

<!-- add this to you menu -->
<item action="statistico"/>

<!-- Statistico -->
<action type="custom" class="TreeHouse\ConanStatisticoBundle\Action\Statistico" title="statistico" slug="statistico"/>
```

Config:

```yml
# ConanBundle
fm_conan:
  ...
  stylesheets:
    - /bundles/vacaturescms/css/vacatures.css
    - /bundles/treehouseconanstatistico/css/statistico.css
    - /bundles/treehouseconanstatistico/css/metricsgraphics.css
  javascripts:
    - https://cdnjs.cloudflare.com/ajax/libs/d3/3.5.0/d3.js
    - /bundles/treehouseconanstatistico/js/metricsgraphics.js
    - /bundles/treehouseconanstatistico/js/statistico.js

```

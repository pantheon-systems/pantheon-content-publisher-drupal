import {ARTICLE_UPDATE_SUBSCRIPTION, PantheonClient, PublishingLevel} from "@pantheon-systems/pcc-sdk-core";

const params = new URLSearchParams(window.location.search);
const documentId = drupalSettings.path.currentPath.split('/')[3];
const publishingLevel = drupalSettings.pantheon_content_publisher.publishing_level || 'REALTIME';
const versionId = drupalSettings.pantheon_content_publisher.version_id;

const pantheonClient = new PantheonClient({
    siteId: window.drupalSettings.pantheon_content_publisher.site_id,
    token: 'pcc_grant ' + params.get('pccGrant')
});

const subscriptionVariables = {
    id: documentId,
    contentType: "TREE_PANTHEON_V2",
    publishingLevel: publishingLevel,
};

if (versionId) {
    subscriptionVariables.versionId = versionId;
}

// Only subscribe for REALTIME, not for DRAFT
if (publishingLevel === 'REALTIME') {
    pantheonClient.apolloClient.subscribe({
        query: ARTICLE_UPDATE_SUBSCRIPTION,
        variables: subscriptionVariables,
    })
    .subscribe({
        next: ({ data }) => {
            if (!data) return;
            // const entryTitle = document.querySelector('h1');
            // entryTitle.innerHTML = article.title;

            const previewContentContainer = document.getElementById('pantheon-content-publisher-preview');
            previewContentContainer.innerHTML = '';
            previewContentContainer.appendChild(generateHTMLFromJSON(JSON.parse(data.article.content)));
        },
    });
}

function generateHTMLFromJSON(json, parentElement = null) {
    const createElement = (tag, attrs = {}, styles = {}, content = '') => {
        if (undefined === tag) {
            tag = 'div';
        }
        const element = document.createElement(tag);

        // Set attributes
        Object.entries(attrs).forEach(([k, v]) => element.setAttribute(k, v));

        // Set styles
        if (Array.isArray(styles)) {
            styles.forEach(style => {
                const [key, value] = style.split(':').map(s => s.trim());
                element.style[key] = value;
            });
        } else if (styles && typeof styles === 'object') {
            Object.entries(styles).forEach(([k, v]) => element.style[k] = v);
        }

        if (content !== null) {
            element.innerHTML = content;
        }

        return element;
    };

    const processNode = (node, parent, uniqueClass) => {
        const { tag, data, style } = node;
        const children = node.children ?? [];
        const attrs = node.attrs ?? {};

        if (tag === 'component' && node.type) {
            const element = createElement('div');
            parent.appendChild(element);
            fetch(Drupal.url('api/pantheoncloud/component/' + node.type + '?snippet=1&attrs=' + window.btoa(JSON.stringify(attrs))))
                .then(async response => response.ok ? element.outerHTML = await response.text() : console.error('Component does not load'));
            return;
        }

        if (!children.length && !data && !Object.keys(attrs).length) {
            return;
        }

        // Scope styles if the tag is 'style'
        const element = createElement(tag, attrs, style, tag === 'style' ? `.${uniqueClass} ${data}` : data);

        children.forEach(child => processNode(child, element, uniqueClass));

        parent.appendChild(element);
    };

    // Create a container if parentElement is not provided
    const container = parentElement || document.createElement('div');

    // Generate a unique class name for scoping
    const uniqueClass = 'scoped-' + Math.random().toString(36).substr(2, 9);
    container.classList.add(uniqueClass);

    processNode(json, container, uniqueClass);

    return container;
}

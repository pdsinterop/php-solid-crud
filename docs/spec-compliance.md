## [W3C Linked Data Platform Spec](https://www.w3.org/TR/ldp/)

- **LDPR** Linked Data Platform Resource
- **LDP-RS** Linked Data Platform RDF Source
- **LDP-NR** Linked Data Platform Non-RDF Source
<!-- - **LDPC** Linked Data Platform Container -->

### Linked Data Platform Resources

- ![4.2.1.1][4.2.1.1] LDP servers MUST at least be HTTP/1.1 conformant servers [RFC7230].

- ![4.2.1.2][4.2.1.2] LDP servers MAY host a mixture of LDP-RSs and LDP-NRs. For example, it is common for LDP servers to need to host binary or text resources that do not have useful RDF representations.

- ![4.2.1.3][4.2.1.3] LDP server responses MUST use entity tags (either weak or strong ones) as response ETag header values, for responses that contain resource representations or successful responses to HTTP HEAD requests.

- ![4.2.1.4][4.2.1.4] LDP servers exposing LDPRs MUST advertise their LDP support by exposing a HTTP Link header with a target URI of http://www.w3.org/ns/ldp#Resource, and a link relation type of type (that is, rel="type") in all responses to requests made to an LDPR's HTTP Request-URI [RFC5988].

- ![4.2.1.5][4.2.1.5] LDP servers MUST assign the default base-URI for [RFC3987] relative-URI resolution to be the HTTP Request-URI when the resource already exists, and to the URI of the created resource when the request results in the creation of a new resource.

- ![4.2.1.6][4.2.1.6] LDP servers MUST publish any constraints on LDP clients’ ability to create or update LDPRs, by adding a Link header with an appropriate context URI, a link relation of http://www.w3.org/ns/ldp#constrainedBy, and a target URI identifying a set of constraints [RFC5988], to all responses to requests that fail due to violation of those constraints. For example, a server that refuses resource creation requests via HTTP PUT, POST, or PATCH would return this Link header on its 4xx responses to such requests. The same Link header MAY be provided on other responses. LDP neither defines nor constrains the representation of the link's target resource. Natural language constraint documents are therefore permitted, although machine-readable ones facilitate better client interactions. The appropriate context URI can vary based on the request's semantics and method; unless the response is otherwise constrained, the default (the effective request URI) SHOULD be used.

- ![4.2.2.1][4.2.2.1] LDP servers MUST support the HTTP GET method for LDPRs.

- ![4.2.2.2][4.2.2.2] LDP servers MUST support the HTTP response headers defined in section 4.2.8 HTTP OPTIONS for the HTTP GET method.

- ![4.2.4.1][4.2.4.1] If a HTTP PUT is accepted on an existing resource, LDP servers MUST replace the entire persistent state of the identified resource with the entity representation in the body of the request. LDP servers MAY ignore LDP-server-managed properties, and MAY ignore other properties such as dcterms:modified and dcterms:creator if they are handled specially by the server (for example, if the server overrides the value or supplies a default value). Any LDP servers that wish to support a more sophisticated merge of data provided by the client with existing state stored on the server for a resource MUST use HTTP PATCH, not HTTP PUT.

- ![4.2.4.2][4.2.4.2] LDP servers SHOULD allow clients to update resources without requiring detailed knowledge of server-specific constraints. This is a consequence of the requirement to enable simple creation and modification of LDPRs.

- ![4.2.4.3][4.2.4.3] If an otherwise valid HTTP PUT request is received that attempts to change properties the server does not allow clients to modify, LDP servers MUST fail the request by responding with a 4xx range status code (typically 409 Conflict). LDP servers SHOULD provide a corresponding response body containing information about which properties could not be persisted. The format of the 4xx response body is not constrained by LDP.

- ![4.2.4.4][4.2.4.4] If an otherwise valid HTTP PUT request is received that contains properties the server chooses not to persist, e.g. unknown content, LDP servers MUST respond with an appropriate 4xx range status code [RFC7231]. LDP servers SHOULD provide a corresponding response body containing information about which properties could not be persisted. The format of the 4xx response body is not constrained by LDP. LDP servers expose these application-specific constraints as described in section 4.2.1 General.

- ![4.2.4.5][4.2.4.5] LDP clients SHOULD use the HTTP If-Match header and HTTP ETags to ensure it isn’t modifying a resource that has changed since the client last retrieved its representation. LDP servers SHOULD require the HTTP If-Match header and HTTP ETags to detect collisions. LDP servers MUST respond with status code 412 (Condition Failed) if ETags fail to match when there are no other errors with the request [RFC7232]. LDP servers that require conditional requests MUST respond with status code 428 (Precondition Required) when the absence of a precondition is the only reason for rejecting the request [RFC6585].

- ![4.2.4.6][4.2.4.6] LDP servers MAY choose to allow the creation of new resources using HTTP PUT.

- ![4.2.6.1][4.2.6.1] LDP servers MUST support the HTTP HEAD method.

- ![4.2.7.1][4.2.7.1] LDP servers that support PATCH MUST include an Accept-Patch HTTP response header [RFC5789] on HTTP OPTIONS requests, listing patch document media type(s) supported by the server.

- ![4.2.8.1][4.2.8.1] LDP servers MUST support the HTTP OPTIONS method.

- ![4.2.8.2][4.2.8.2] LDP servers MUST indicate their support for HTTP Methods by responding to a HTTP OPTIONS request on the LDPR’s URL with the HTTP Method tokens in the HTTP response header Allow.

- ![4.3.1.1][4.3.1.1] Each LDP RDF Source MUST also be a conforming LDP Resource as defined in section 4.2 Resource, along with the restrictions in this section. LDP clients MAY infer the following triple: one whose subject is the LDP-RS, whose predicate is rdf:type, and whose object is ldp:Resource, but there is no requirement to materialize this triple in the LDP-RS representation.

- ![4.3.1.2][4.3.1.2] LDP-RSs representations SHOULD have at least one rdf:type set explicitly. This makes the representations much more useful to client applications that don’t support inferencing.

- ![4.3.1.3][4.3.1.3] The representation of a LDP-RS MAY have an rdf:type of ldp:RDFSource for Linked Data Platform RDF Source.

- ![4.3.1.4][4.3.1.4] LDP servers MUST provide an RDF representation for LDP-RSs. The HTTP Request-URI of the LDP-RS is typically the subject of most triples in the response.

- ![4.3.1.5][4.3.1.5] LDP-RSs SHOULD reuse existing vocabularies instead of creating their own duplicate vocabulary terms. In addition to this general rule, some specific cases are covered by other conformance rules.

- ![4.3.1.6][4.3.1.6] LDP-RSs predicates SHOULD use standard vocabularies such as Dublin Core [DC-TERMS], RDF [rdf11-concepts] and RDF Schema [rdf-schema], whenever possible.

- ![4.3.1.7][4.3.1.7] In the absence of special knowledge of the application or domain, LDP clients MUST assume that any LDP-RS can have multiple rdf:type triples with different objects.

- ![4.3.1.8][4.3.1.8] In the absence of special knowledge of the application or domain, LDP clients MUST assume that the rdf:type values of a given LDP-RS can change over time.

- ![4.3.1.9][4.3.1.9] LDP clients SHOULD always assume that the set of predicates for a LDP-RS of a particular type at an arbitrary server is open, in the sense that different resources of the same type MAY not all have the same set of predicates in their triples, and the set of predicates that are used in the state of any one LDP-RS is not limited to any pre-defined set.

- ![4.3.1.10][4.3.1.10] LDP servers MUST not require LDP clients to implement inferencing in order to recognize the subset of content defined by LDP. Other specifications built on top of LDP MAY require clients to implement inferencing [rdf11-concepts]. The practical implication is that all content defined by LDP MUST be explicitly represented, unless noted otherwise within this document.

- ![4.3.1.11][4.3.1.11] A LDP client MUST preserve all triples retrieved from a LDP-RS using HTTP GET that it doesn’t change whether it understands the predicates or not, when its intent is to perform an update using HTTP PUT. The use of HTTP PATCH instead of HTTP PUT for update avoids this burden for clients [RFC5789].

- ![4.3.1.12][4.3.1.12] LDP clients MAY provide LDP-defined hints that allow servers to optimize the content of responses. section 7.2 Preferences on the Prefer Request Header defines hints that apply to LDP-RSs.

- ![4.3.1.13][4.3.1.13] LDP clients MUST be capable of processing responses formed by a LDP server that ignores hints, including LDP-defined hints.

- ![4.3.2.1][4.3.2.1] LDP servers MUST respond with a Turtle representation of the requested LDP-RS when the request includes an Accept header specifying text/turtle, unless HTTP content negotiation requires a different outcome [turtle].

- ![4.3.2.2][4.3.2.2] LDP servers SHOULD respond with a text/turtle representation of the requested LDP-RS whenever the Accept request header is absent [turtle].

- ![4.3.2.3][4.3.2.3] LDP servers MUST respond with a application/ld+json representation of the requested LDP-RS when the request includes an Accept header, unless content negotiation or Turtle support requires a different outcome [JSON-LD].

- ![4.4.1.1][4.4.1.1] Each LDP Non-RDF Source MUST also be a conforming LDP Resource in section 4.2 Resource. LDP Non-RDF Sources MAY not be able to fully express their state using RDF [rdf11-concepts].

- ![4.4.1.2][4.4.1.2] LDP servers exposing an LDP Non-RDF Source MAY advertise this by exposing a HTTP Link header with a target URI of http://www.w3.org/ns/ldp#NonRDFSource, and a link relation type of type (that is, rel="type") in responses to requests made to the LDP-NR's HTTP Request-URI [RFC5988].

### Linked Data Platform Containers

- ![5.2.1.1][5.2.1.1] Each Linked Data Platform Container MUST also be a conforming Linked Data Platform RDF Source. LDP clients MAY infer the following triple: one whose subject is the LDPC, whose predicate is rdf:type, and whose object is ldp:RDFSource, but there is no requirement to materialize this triple in the LDPC representation.

- ![5.2.1.2][5.2.1.2] The representation of a LDPC MAY have an rdf:type of ldp:Container for Linked Data Platform Container. Non-normative note: LDPCs might have additional types, like any LDP-RS.

- ![5.2.1.3][5.2.1.3] LDPC representations SHOULD not use RDF container types rdf:Bag, rdf:Seq or rdf:List.

- ![5.2.1.4][5.2.1.4] LDP servers exposing LDPCs MUST advertise their LDP support by exposing a HTTP Link header with a target URI matching the type of container (see below) the server supports, and a link relation type of type (that is, rel="type") in all responses to requests made to the LDPC's HTTP Request-URI. LDP servers MAY provide additional HTTP Link: rel="type" headers. The notes on the corresponding LDPR constraint apply equally to LDPCs.

- ![5.2.1.5][5.2.1.5] LDP servers SHOULD respect all of a client's LDP-defined hints, for example which subsets of LDP-defined state the client is interested in processing, to influence the set of triples returned in representations of a LDPC, particularly for large LDPCs. See also [LDP-PAGING].

- ![5.2.3.1][5.2.3.1] LDP clients SHOULD create member resources by submitting a representation as the entity body of the HTTP POST to a known LDPC. If the resource was created successfully, LDP servers MUST respond with status code 201 (Created) and the Location header set to the new resource’s URL. Clients shall not expect any representation in the response entity body on a 201 (Created) response.

- ![5.2.3.2][5.2.3.2] When a successful HTTP POST request to a LDPC results in the creation of a LDPR, a containment triple MUST be added to the state of the LDPC whose subject is the LDPC URI, whose predicate is ldp:contains and whose object is the URI for the newly created document (LDPR). Other triples MAY be added as well. The newly created LDPR appears as a contained resource of the LDPC until the newly created document is deleted or removed by other methods.

- ![5.2.3.3][5.2.3.3] LDP servers MAY accept an HTTP POST of non-RDF representations (LDP-NRs) for creation of any kind of resource, for example binary resources. See the Accept-Post section for details on how clients can discover whether a LDPC supports this behavior.

- ![5.2.3.4][5.2.3.4] LDP servers that successfully create a resource from a RDF representation in the request entity body MUST honor the client's requested interaction model(s). If any requested interaction model cannot be honored, the server MUST fail the request.

- ![5.2.3.5][5.2.3.5] LDP servers that allow creation of LDP-RSs via POST MUST allow clients to create new members by enclosing a request entity body with a Content-Type request header whose value is text/turtle [turtle].

- ![5.2.3.6][5.2.3.6] LDP servers SHOULD use the Content-Type request header to determine the request representation's format when the request has an entity body.

- ![5.2.3.7][5.2.3.7] LDP servers creating a LDP-RS via POST MUST interpret the null relative URI for the subject of triples in the LDP-RS representation in the request entity body as identifying the entity in the request body. Commonly, that entity is the model for the "to be created" LDPR, so triples whose subject is the null relative URI result in triples in the created resource whose subject is the created resource.

- ![5.2.3.8][5.2.3.8] LDP servers SHOULD assign the URI for the resource to be created using server application specific rules in the absence of a client hint.

- ![5.2.3.9][5.2.3.9] LDP servers SHOULD allow clients to create new resources without requiring detailed knowledge of application-specific constraints. This is a consequence of the requirement to enable simple creation and modification of LDPRs. LDP servers expose these application-specific constraints as described in section 4.2.1 General.

- ![5.2.3.10][5.2.3.10] LDP servers MAY allow clients to suggest the URI for a resource created through POST, using the HTTP Slug header as defined in [RFC5023]. LDP adds no new requirements to this usage, so its presence functions as a client hint to the server providing a desired string to be incorporated into the server's final choice of resource URI.

- ![5.2.3.11][5.2.3.11] LDP servers that allow member creation via POST SHOULD not re-use URIs.

- ![5.2.3.12][5.2.3.12] Upon successful creation of an LDP-NR (HTTP status code of 201-Created and URI indicated by Location response header), LDP servers MAY create an associated LDP-RS to contain data about the newly created LDP-NR. If a LDP server creates this associated LDP-RS, it MUST indicate its location in the response by adding a HTTP Link header with a context URI identifying the newly created LDP-NR (instead of the effective request URI), a link relation value of describedby, and a target URI identifying the associated LDP-RS resource [RFC5988].

- ![5.2.3.13][5.2.3.13] LDP servers that support POST MUST include an Accept-Post response header on HTTP OPTIONS responses, listing POST request media type(s) supported by the server. LDP only specifies the use of POST for the purpose of creating new resources, but a server can accept POST requests with other semantics. While "POST to create" is a common interaction pattern, LDP clients are not guaranteed, even when making requests to a LDP server, that every successful POST request will result in the creation of a new resource; they MUST rely on out of band information for knowledge of which POST requests, if any, will have the "create new resource" semantics. This requirement on LDP servers is intentionally stronger than the one levied in the header registration; it is unrealistic to expect all existing resources that support POST to suddenly return a new header or for all new specifications constraining POST to be aware of its existence and require it, but it is a reasonable requirement for new specifications such as LDP.

- ![5.2.3.14][5.2.3.14] LDP servers that allow creation of LDP-RSs via POST MUST allow clients to create new members by enclosing a request entity body with a Content-Type request header whose value is application/ld+json [JSON-LD].

- ![5.2.4.1][5.2.4.1] LDP servers SHOULD not allow HTTP PUT to update a LDPC’s containment triples; if the server receives such a request, it SHOULD respond with a 409 (Conflict) status code.

- ![5.2.4.2][5.2.4.2] LDP servers that allow LDPR creation via PUT SHOULD not re-use URIs.

- ![5.2.5.1][5.2.5.1] When a contained LDPR is deleted, the LDPC server MUST also remove the corresponding containment triple, which has the effect of removing the deleted LDPR from the containing LDPC.

- ![5.2.5.2][5.2.5.2] When a contained LDPR is deleted, and the LDPC server created an associated LDP-RS (see the LDPC POST section), the LDPC server MUST also delete the associated LDP-RS it created.

- ![5.2.7.1][5.2.7.1] LDP servers are recommended to support HTTP PATCH as the preferred method for updating a LDPC's minimal-container triples.

- ![5.2.8.1][5.2.8.1] When responding to requests whose request-URI is a LDP-NR with an associated LDP-RS, a LDPC server MUST provide the same HTTP Link response header as is required in the create response.

- ![5.3.1.1][5.3.1.1] Each LDP Basic Container MUST also be a conforming LDP Container in section 5.2 Container along with the following restrictions in this section. LDP clients MAY infer the following triple: whose subject is the LDP Basic Container, whose predicate is rdf:type, and whose object is ldp:Container, but there is no requirement to materialize this triple in the LDP-BC representation.

- ![5.4.1.1][5.4.1.1] Each LDP Direct Container MUST also be a conforming LDP Container in section 5.2 Container along the following restrictions. LDP clients MAY infer the following triple: whose subject is the LDP Direct Container, whose predicate is rdf:type, and whose object is ldp:Container, but there is no requirement to materialize this triple in the LDP-DC representation.

- ![5.4.1.2][5.4.1.2] LDP Direct Containers SHOULD use the ldp:member predicate as a LDPC's membership predicate if there is no obvious predicate from an application vocabulary to use. The state of a LDPC includes information about which resources are its members, in the form of membership triples that follow a consistent pattern. The LDPC's state contains enough information for clients to discern the membership predicate, the other consistent membership value used in the container's membership triples (membership-constant-URI), and the position (subject or object) where those URIs occurs in the membership triples. Member resources can be any kind of resource identified by a URI, LDPR or otherwise.

- ![5.4.1.3][5.4.1.3] Each LDP Direct Container representation MUST contain exactly one triple whose subject is the LDPC URI, whose predicate is the ldp:membershipResource, and whose object is the LDPC's membership-constant-URI. Commonly the LDPC's URI is the membership-constant-URI, but LDP does not require this.

- ![5.4.1.4][5.4.1.4] Each LDP Direct Container representation MUST contain exactly one triple whose subject is the LDPC URI, and whose predicate is either ldp:hasMemberRelation or ldp:isMemberOfRelation. The object of the triple is constrained by other sections, such as ldp:hasMemberRelation or ldp:isMemberOfRelation, based on the membership triple pattern used by the container.

- ![5.4.1.4.1][5.4.1.4.1] LDP Direct Containers whose membership triple pattern is ( membership-constant-URI , membership-predicate , member-derived-URI ) MUST contain exactly one triple whose subject is the LDPC URI, whose predicate is ldp:hasMemberRelation, and whose object is the URI of membership-predicate.

- ![5.4.1.4.2][5.4.1.4.2] LDP Direct Containers whose membership triple pattern is ( member-derived-URI , membership-predicate , membership-constant-URI ) MUST contain exactly one triple whose subject is the LDPC URI, whose predicate is ldp:isMemberOfRelation, and whose object is the URI of membership-predicate.

- ![5.4.1.5][5.4.1.5] LDP Direct Containers MUST behave as if they have a ( LDPC URI, ldp:insertedContentRelation , ldp:MemberSubject ) triple, but LDP imposes no requirement to materialize such a triple in the LDP-DC representation. The value ldp:MemberSubject means that the member-derived-URI is the URI assigned by the server to a document it creates; for example, if the client POSTs content to a container that causes the container to create a new LDPR, ldp:MemberSubject says that the member-derived-URI is the URI assigned to the newly created LDPR.

- ![5.4.2.1][5.4.2.1] When a successful HTTP POST request to a LDPC results in the creation of a LDPR, the LDPC MUST update its membership triples to reflect that addition, and the resulting membership triple MUST be consistent with any LDP-defined predicates it exposes. A LDP Direct Container's membership triples MAY also be modified via through other means.

- ![5.4.3.1][5.4.3.1] When a LDPR identified by the object of a membership triple which was originally created by the LDP-DC is deleted, the LDPC server MUST also remove the corresponding membership triple.

- ![5.5.1.1][5.5.1.1] Each LDP Indirect Container MUST also be a conforming LDP Direct Container as described in section 5.4 Direct, along with the following restrictions. LDP clients MAY infer the following triple: one whose subject is LDP Indirect Container, whose predicate is rdf:type, and whose object is ldp:Container, but there is no requirement to materialize this triple in the LDP-IC representation.

- ![5.5.1.2][5.5.1.2] LDP Indirect Containers MUST contain exactly one triple whose subject is the LDPC URI, whose predicate is ldp:insertedContentRelation, and whose object ICR describes how the member-derived-URI in the container's membership triples is chosen. The member-derived-URI is taken from some triple ( S, P, O ) in the document supplied by the client as input to the create request; if ICR's value is P, then the member-derived-URI is O. LDP does not define the behavior when more than one triple containing the predicate P is present in the client's input. For example, if the client POSTs RDF content to a container that causes the container to create a new LDP-RS, and that content contains the triple ( <> , foaf:primaryTopic , bob#me ) foaf:primaryTopic says that the member-derived-URI is bob#me. One consequence of this definition is that indirect container member creation is only well-defined by LDP when the document supplied by the client as input to the create request has an RDF media type.

- ![5.5.2.1][5.5.2.1] LDPCs whose ldp:insertedContentRelation triple has an object other than ldp:MemberSubject and that create new resources MUST add a triple to the container whose subject is the container's URI, whose predicate is ldp:contains, and whose object is the newly created resource's URI (which will be different from the member-derived URI in this case). This ldp:contains triple can be the only link from the container to the newly created resource in certain cases.

- ![6.1.1][6.1.1] LDPC membership is not exclusive; this means that the same resource (LDPR or not) can be a member of more than one LDPC.

- ![6.1.2][6.1.2] LDP servers SHOULD not re-use URIs, regardless of the mechanism by which members are created (POST, PUT, etc.). Certain specific cases exist where a LDPC server might delete a resource and then later re-use the URI when it identifies the same resource, but only when consistent with Web architecture. While it is difficult to provide absolute implementation guarantees of non-reuse in all failure scenarios, re-using URIs creates ambiguities for clients that are best avoided.

- ![6.2.1][6.2.1] LDP servers can support representations beyond those necessary to conform to this specification. These could be other RDF formats, like N3 or NTriples, but non-RDF formats like HTML [HTML401] and JSON [RFC4627] would likely be common. HTTP content negotiation ([RFC7231] Section 3.4 - Content Negotiation) is used to select the format.

- ![6.2.2][6.2.2] LDPRs can be created, updated and deleted using methods not defined in this document, for example through application-specific means, SPARQL UPDATE, etc. [SPARQL-UPDATE], as long as those methods do not conflict with this specification's normative requirements.

- ![6.2.3][6.2.3] LDP servers remove the resource identified by the Request-URI in response to a successful HTTP DELETE request. After such a request, a subsequent HTTP GET on the same Request-URI usually results in a 404 (Not found) or 410 (Gone) status code, although HTTP allows others.

- ![6.2.4][6.2.4] LDP servers can alter the state of other resources as a result of any HTTP request, especially when non-safe methods are used ([RFC7231] section 4.2.1). For example, it is acceptable for the server to remove triples from other resources whose subject or object is the deleted resource as the result of a successful HTTP DELETE request. It is also acceptable and common for LDP servers to not do this – the server's behavior can vary, so LDP clients cannot depend on it.

- ![6.2.5][6.2.5] LDP servers can implement HTTP PATCH to allow modifications, especially partial replacement, of their resources. No minimal set of patch document formats is mandated by this document or by the definition of PATCH [RFC5789].

- ![6.2.6][6.2.6] When the Content-Type request header is absent from a request, LDP servers might infer the content type by inspecting the entity body contents ([RFC7231] section 3.1.1.5).

- ![6.3.1][6.3.1] The state of a LDPR can have triples with any subject(s). The URL used to retrieve the representation of a LDPR need not be the subject of any of its triples.

- ![6.3.2][6.3.2] The representation of a LDPC can include an arbitrary number of additional triples whose subjects are the members of the container, or that are from the representations of the members (if they have RDF representations). This allows an LDP server to provide clients with information about the members without the client having to do a GET on each member individually.

- ![6.3.3][6.3.3] The state of a LDPR can have more than one triple with an rdf:type predicate.

- ![7.1.1][7.1.1] The syntax for Accept-Post, using the ABNF syntax defined in Section 1.2 of [RFC7231], is:

- ![7.1.2][7.1.2] The Accept-Post HTTP header SHOULD appear in the OPTIONS response for any resource that supports the use of the POST method. The presence of the Accept-Post header in response to any method is an implicit indication that POST is allowed on the resource identified by the Request-URI. The presence of a specific document format in this header indicates that that specific format is allowed on POST requests to the resource identified by the Request-URI.

- ![7.2.2.1][7.2.2.1] The include hint defines a subset of a LDPR's content that a client would like included in a representation. The syntax for the include parameter of the HTTP Prefer request header's return=representation preference [RFC7240] is:

- ![7.2.2.2][7.2.2.2] The omit hint defines a subset of a LDPR's content that a client would like omitted from a representation. The syntax for the omit parameter of the HTTP Prefer request header's return=representation preference [RFC7240] is:

- ![7.2.2.3][7.2.2.3] When LDP servers receive a request with conflicting hints, this specification imposes no requirements on their behavior. They are free to reject the request, process it applying some subset of the hints, or anything else appropriate to the server. [RFC7240] suggests treating similar requests as though none of the conflicting preferences were specified.

- ![7.2.2.4][7.2.2.4] This specification defines the following URIs for clients to use with include and omit parameters. It assigns no meaning to other URIs, although other specifications MAY do so.

## Sources

[4.2.1.1]: https://img.shields.io/badge/-4.2.1.1-015a9c
[4.2.1.2]: https://img.shields.io/badge/-4.2.1.2-015a9c
[4.2.1.3]: https://img.shields.io/badge/-4.2.1.3-015a9c
[4.2.1.4]: https://img.shields.io/badge/-4.2.1.4-015a9c
[4.2.1.5]: https://img.shields.io/badge/-4.2.1.5-015a9c
[4.2.1.6]: https://img.shields.io/badge/-4.2.1.6-015a9c
[4.2.2.1]: https://img.shields.io/badge/-4.2.2.1-015a9c
[4.2.2.2]: https://img.shields.io/badge/-4.2.2.2-015a9c
[4.2.4.1]: https://img.shields.io/badge/-4.2.4.1-015a9c
[4.2.4.2]: https://img.shields.io/badge/-4.2.4.2-015a9c
[4.2.4.3]: https://img.shields.io/badge/-4.2.4.3-015a9c
[4.2.4.4]: https://img.shields.io/badge/-4.2.4.4-015a9c
[4.2.4.5]: https://img.shields.io/badge/-4.2.4.5-015a9c
[4.2.4.6]: https://img.shields.io/badge/-4.2.4.6-015a9c
[4.2.6.1]: https://img.shields.io/badge/-4.2.6.1-015a9c
[4.2.7.1]: https://img.shields.io/badge/-4.2.7.1-015a9c
[4.2.8.1]: https://img.shields.io/badge/-4.2.8.1-015a9c
[4.2.8.2]: https://img.shields.io/badge/-4.2.8.2-015a9c
[4.3.1.1]: https://img.shields.io/badge/-4.3.1.1-015a9c
[4.3.1.2]: https://img.shields.io/badge/-4.3.1.2-015a9c
[4.3.1.3]: https://img.shields.io/badge/-4.3.1.3-015a9c
[4.3.1.4]: https://img.shields.io/badge/-4.3.1.4-015a9c
[4.3.1.5]: https://img.shields.io/badge/-4.3.1.5-015a9c
[4.3.1.6]: https://img.shields.io/badge/-4.3.1.6-015a9c
[4.3.1.7]: https://img.shields.io/badge/-4.3.1.7-015a9c
[4.3.1.8]: https://img.shields.io/badge/-4.3.1.8-015a9c
[4.3.1.9]: https://img.shields.io/badge/-4.3.1.9-015a9c
[4.3.1.10]: https://img.shields.io/badge/-4.3.1.10-015a9c
[4.3.1.11]: https://img.shields.io/badge/-4.3.1.11-015a9c
[4.3.1.12]: https://img.shields.io/badge/-4.3.1.12-015a9c
[4.3.1.13]: https://img.shields.io/badge/-4.3.1.13-015a9c
[4.3.2.1]: https://img.shields.io/badge/-4.3.2.1-015a9c
[4.3.2.2]: https://img.shields.io/badge/-4.3.2.2-015a9c
[4.3.2.3]: https://img.shields.io/badge/-4.3.2.3-015a9c
[4.4.1.1]: https://img.shields.io/badge/-4.4.1.1-015a9c
[4.4.1.2]: https://img.shields.io/badge/-4.4.1.2-015a9c
[5.2.1.1]: https://img.shields.io/badge/-5.2.1.1-015a9c
[5.2.1.2]: https://img.shields.io/badge/-5.2.1.2-015a9c
[5.2.1.3]: https://img.shields.io/badge/-5.2.1.3-015a9c
[5.2.1.4]: https://img.shields.io/badge/-5.2.1.4-015a9c
[5.2.1.5]: https://img.shields.io/badge/-5.2.1.5-015a9c
[5.2.3.1]: https://img.shields.io/badge/-5.2.3.1-015a9c
[5.2.3.2]: https://img.shields.io/badge/-5.2.3.2-015a9c
[5.2.3.3]: https://img.shields.io/badge/-5.2.3.3-015a9c
[5.2.3.4]: https://img.shields.io/badge/-5.2.3.4-015a9c
[5.2.3.5]: https://img.shields.io/badge/-5.2.3.5-015a9c
[5.2.3.6]: https://img.shields.io/badge/-5.2.3.6-015a9c
[5.2.3.7]: https://img.shields.io/badge/-5.2.3.7-015a9c
[5.2.3.8]: https://img.shields.io/badge/-5.2.3.8-015a9c
[5.2.3.9]: https://img.shields.io/badge/-5.2.3.9-015a9c
[5.2.3.10]: https://img.shields.io/badge/-5.2.3.10-015a9c
[5.2.3.11]: https://img.shields.io/badge/-5.2.3.11-015a9c
[5.2.3.12]: https://img.shields.io/badge/-5.2.3.12-015a9c
[5.2.3.13]: https://img.shields.io/badge/-5.2.3.13-015a9c
[5.2.3.14]: https://img.shields.io/badge/-5.2.3.14-015a9c
[5.2.4.1]: https://img.shields.io/badge/-5.2.4.1-015a9c
[5.2.4.2]: https://img.shields.io/badge/-5.2.4.2-015a9c
[5.2.5.1]: https://img.shields.io/badge/-5.2.5.1-015a9c
[5.2.5.2]: https://img.shields.io/badge/-5.2.5.2-015a9c
[5.2.7.1]: https://img.shields.io/badge/-5.2.7.1-015a9c
[5.2.8.1]: https://img.shields.io/badge/-5.2.8.1-015a9c
[5.3.1.1]: https://img.shields.io/badge/-5.3.1.1-015a9c
[5.4.1.1]: https://img.shields.io/badge/-5.4.1.1-015a9c
[5.4.1.2]: https://img.shields.io/badge/-5.4.1.2-015a9c
[5.4.1.3]: https://img.shields.io/badge/-5.4.1.3-015a9c
[5.4.1.4]: https://img.shields.io/badge/-5.4.1.4-015a9c
[5.4.1.4.1]: https://img.shields.io/badge/-5.4.1.4.1-015a9c
[5.4.1.4.2]: https://img.shields.io/badge/-5.4.1.4.2-015a9c
[5.4.1.5]: https://img.shields.io/badge/-5.4.1.5-015a9c
[5.4.2.1]: https://img.shields.io/badge/-5.4.2.1-015a9c
[5.4.3.1]: https://img.shields.io/badge/-5.4.3.1-015a9c
[5.5.1.1]: https://img.shields.io/badge/-5.5.1.1-015a9c
[5.5.1.2]: https://img.shields.io/badge/-5.5.1.2-015a9c
[5.5.2.1]: https://img.shields.io/badge/-5.5.2.1-015a9c
[6.1.1]: https://img.shields.io/badge/-6.1.1-015a9c
[6.1.2]: https://img.shields.io/badge/-6.1.2-015a9c
[6.2.1]: https://img.shields.io/badge/-6.2.1-015a9c
[6.2.2]: https://img.shields.io/badge/-6.2.2-015a9c
[6.2.3]: https://img.shields.io/badge/-6.2.3-015a9c
[6.2.4]: https://img.shields.io/badge/-6.2.4-015a9c
[6.2.5]: https://img.shields.io/badge/-6.2.5-015a9c
[6.2.6]: https://img.shields.io/badge/-6.2.6-015a9c
[6.3.1]: https://img.shields.io/badge/-6.3.1-015a9c
[6.3.2]: https://img.shields.io/badge/-6.3.2-015a9c
[6.3.3]: https://img.shields.io/badge/-6.3.3-015a9c
[7.1.1]: https://img.shields.io/badge/-7.1.1-015a9c
[7.1.2]: https://img.shields.io/badge/-7.1.2-015a9c
[7.2.2.1]: https://img.shields.io/badge/-7.2.2.1-015a9c
[7.2.2.2]: https://img.shields.io/badge/-7.2.2.2-015a9c
[7.2.2.3]: https://img.shields.io/badge/-7.2.2.3-015a9c
[7.2.2.4]: https://img.shields.io/badge/-7.2.2.4-015a9c
